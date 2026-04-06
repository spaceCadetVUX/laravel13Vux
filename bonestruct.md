# Project Decisions — Backbone Shop

> Single seller B2C · Laravel 13 · PHP 8.5 · VPS (Laravel Forge) · Small scale → designed to grow

---

## Stack

- **Framework:** Laravel 13
- **PHP:** 8.5
- **Runtime:** FrankenPHP + Octane
- **Frontend:** Inertia.js + SSR
- **Admin:** Filament 3
- **Testing:** Pest
- **Local dev:** Laragon (PostgreSQL 16 added manually) or Docker Compose

---

## 0.1 Database Fundamentals

| Decision | Chosen | Notes |
|----------|--------|-------|
| Engine | **PostgreSQL 16** | Laragon: add pg16 binaries manually. VPS: Forge installs automatically |
| IDs | **ULID** | `HasUlids` trait on every model. `Str::ulid()` in Laravel built-in |
| Money | **BIGINT cents** | `2999` = $29.99. Use `moneyphp/money` for all arithmetic |
| Timestamps | **UTC** | `APP_TIMEZONE=UTC` in `.env`. Convert to local TZ at display only |
| Soft deletes | **`deleted_at`** on all business tables | Hard delete only: `order_events`, `payment_events`, `audit_log`, `cart_items` |
| Translations | **`spatie/laravel-translatable`** (JSON column) | 1 language now, add more later — zero schema change needed |
| Connection pooling | **PgBouncer** transaction mode | Same VPS, port 6432. Laravel connects to 6432 not 5432 |
| Multi-tenancy | **Single DB** | B2C single seller — no tenancy needed |

---

## 0.2 Product Catalog Design

| Decision | Chosen | Notes |
|----------|--------|-------|
| Product types | **Simple products only** | No variants, no bundles, no digital — clean schema. Add variants later if needed |
| Attribute model | **JSON column** on `products` | `attributes JSONB` — filterable via Meilisearch, no EAV complexity |
| Category model | **`parent_id` self-ref + `lazychaser/laravel-nestedset`** | 2 levels (category → sub). Package handles tree queries, allows 3+ levels later free |
| SKU ownership | **Per product** | No variants = one SKU per product |
| Pricing model | **`price` + `sale_price` + date range** | `price`, `sale_price` (nullable), `sale_starts_at`, `sale_ends_at` on product table |
| Digital products | **No** | Physical goods only |
| Bundles / kits | **No** | Not needed now. Add `bundle_items` table later if required |
| Subscriptions | **No** | One-time purchases only |

**Key schema fields on `products`:**
```
price           BIGINT NOT NULL          -- regular price (cents)
sale_price      BIGINT NULL              -- promo price (cents), null = no sale
sale_starts_at  TIMESTAMPTZ NULL
sale_ends_at    TIMESTAMPTZ NULL
attributes      JSONB NULL               -- {"color": "blue", "weight": "200g"}
```

**Rich Text Description:**
- Editor: **TinyMCE** (or Filament's built-in `RichEditor` / `TiptapEditor` plugin)
- Storage: `description` as **`TEXT`** column (HTML content, no length limit)
- Since translatable → stored as **`JSONB`** via `spatie/laravel-translatable`
- **XSS sanitization on save** — always purify HTML before storing: use `stevebauman/purify` (HTMLPurifier wrapper for Laravel)
- Images inside description → upload to S3/R2, never base64 inline

```
-- translatable fields (JSONB, handles multilingual + rich text):
name            JSONB NOT NULL   -- {"en": "Product Name"}
description     JSONB NULL       -- {"en": "<p>Full HTML...</p>"}
short_desc      JSONB NULL       -- {"en": "One-line summary for listing pages"}
slug            JSONB NOT NULL   -- {"en": "product-slug"}
meta_title      JSONB NULL       -- SEO title override
meta_desc       JSONB NULL       -- SEO meta description
```

**Filament integration:** use `FilamentTiptapEditor` or `RichEditor` field in the Filament resource — connects directly to TinyMCE/Tiptap, output is sanitized HTML saved to `description`.

---

## 0.3 Inventory

| Decision | Chosen | Notes |
|----------|--------|-------|
| Stock model | **Per product** | No variants = stock lives on `products` table directly. Separate `inventory` table ready if variants added later |
| Multi-warehouse | **No — single location** | One stock number per product. No `warehouses` table needed now |
| Reservation model | **Reserve on checkout confirm** | Stock locked on `OrderPlaced`. Released immediately if payment fails or order cancelled |
| Oversell policy | **Backorder flag per product** | `allow_backorder BOOLEAN` on `products`. When `true` → orders accepted at zero stock |
| Flash sale locking | **DB row lock** (`SELECT FOR UPDATE`) | Sufficient for small-medium scale. Upgrade to Redis atomic decrement if traffic spikes |
| Stock sync | **Manual now, interface ready** | `UpdateStock` action class handles all stock changes. Plug in webhook handler later without rewrite |

**Key schema fields on `products`:**
```
qty_on_hand       INT NOT NULL DEFAULT 0
qty_reserved      INT NOT NULL DEFAULT 0   -- locked at checkout, freed on cancel/fail
allow_backorder   BOOLEAN NOT NULL DEFAULT false
low_stock_alert   INT NULL                 -- notify admin when qty_on_hand <= this
last_synced_at    TIMESTAMPTZ NULL         -- NULL = manual, set when ERP syncs
```

**Available stock formula:**
```
qty_available = qty_on_hand - qty_reserved
-- if allow_backorder = true: always allow order even if qty_available <= 0
```

**Stock change flow:**
```
Checkout confirmed → qty_reserved += qty
Payment success   → qty_on_hand  -= qty, qty_reserved -= qty
Payment failed    → qty_reserved -= qty  (release)
Order cancelled   → qty_on_hand  += qty  (if already deducted)
```

---

## 0.4 Orders & Checkout

| Decision | Chosen | Notes |
|----------|--------|-------|
| Guest checkout | **Yes** | `session_id` for guests, `user_id` (nullable) for logged-in. Email required either way |
| Cart storage | **DB** | `carts` + `cart_items` tables. Not session/Redis — survives browser close, device switch |
| Cart merge | **Yes** | Guest cart merges into user cart on login. Qty conflicts → take higher qty |
| Order state source | **Append-only `order_events`** | `orders.status` is derived. Full audit trail, replayable. Never update past events |
| Order immutability | **Immutable snapshot** | Copy `price`, `product_name`, `sku` into `order_items` at checkout. Never join live product |
| Multi-vendor | **No — single seller** | No `seller_id`. No commission. No vendor payouts |
| Returns / RMA | **Simple flag on order** | `return_requested_at`, `return_reason`, `refunded_at`, `refund_amount` on `orders` table |
| Tax engine | **Tax inclusive — no calculation** | Displayed price = final price. No tax engine needed. Add `tax_inclusive BOOLEAN DEFAULT true` for future flexibility |

**Key schema fields on `orders`:**
```
id                  ULID PK
user_id             ULID NULL FK → users     -- NULL = guest order
session_id          TEXT NULL               -- guest identifier
status              TEXT NOT NULL           -- derived from last order_event
email               TEXT NOT NULL           -- always required (guest or user)
subtotal            BIGINT NOT NULL         -- sum of items (cents)
discount_total      BIGINT NOT NULL DEFAULT 0
shipping_total      BIGINT NOT NULL DEFAULT 0
grand_total         BIGINT NOT NULL         -- what customer paid
currency            CHAR(3) NOT NULL DEFAULT 'USD'
tax_inclusive       BOOLEAN DEFAULT true    -- prices include tax
shipping_address    JSONB NOT NULL          -- snapshot at order time
billing_address     JSONB NOT NULL          -- snapshot at order time
notes               TEXT NULL               -- customer notes
return_requested_at TIMESTAMPTZ NULL
return_reason       TEXT NULL
refunded_at         TIMESTAMPTZ NULL
refund_amount       BIGINT NULL             -- partial or full (cents)
```

**`order_items` — immutable snapshot:**
```
id                  ULID PK
order_id            ULID FK → orders
product_id          ULID FK → products     -- reference only, never join for display
product_name        TEXT NOT NULL          -- snapshot
product_sku         TEXT NOT NULL          -- snapshot
unit_price          BIGINT NOT NULL        -- price at time of order (cents)
qty                 INT NOT NULL
line_total          BIGINT NOT NULL        -- unit_price × qty
```

**Order state machine:**
```
pending_payment → payment_authorized → confirmed → processing → shipped → delivered → completed
                                           │                                              │
                                           └──────────────── cancelled ──────────────────┘
                                                                                          │
                                                                              return_requested → refunded
```

**`order_events` — append-only:**
```
id          ULID PK
order_id    ULID FK → orders
event       TEXT          -- 'order.placed', 'payment.authorized', 'order.shipped' ...
payload     JSONB         -- full context snapshot
created_at  TIMESTAMPTZ
```

---

## 0.5 Payments

| Decision | Chosen | Notes |
|----------|--------|-------|
| Primary gateway | **VNPay** | Vietnam's leading gateway. Supports domestic bank cards (napas/ATM), Visa, Mastercard, VNPAY-QR |
| Gateway abstraction | **Yes — `PaymentGateway` contract** | Critical with VNPay — isolate behind interface so you can add Stripe/PayPal later without rewrite |
| Payment storage | **Tokenize only** | VNPay handles cards on their portal. You store only `transaction_ref` + `vnp_txn_ref`. Never raw card data |
| Webhook / IPN | **Idempotent queue-based** | VNPay sends IPN callback to your return URL. Must handle duplicates — check `payment_events` before processing |
| Refunds | **Manual via Filament** | Admin triggers refund → calls VNPay refund API → logs to `payment_events` |
| Multi-currency | **No — single currency (VND)** | VNPay works in VND. Store amounts as whole VND integers (VNPay API requires `amount × 100` — handle in gateway driver) |
| Payment methods | **Card + VNPAY-QR** | Cards via VNPay portal redirect. VNPAY-QR for mobile users — high adoption in Vietnam |

**VNPay flow (redirect-based, not embedded):**
```
Checkout → your app builds VNPay redirect URL (signed HMAC)
         → customer redirected to VNPay portal
         → customer pays
         → VNPay redirects back to your return_url (browser)
         → VNPay also sends IPN to your ipn_url (server-to-server)
         → verify HMAC signature on both callbacks
         → dispatch ProcessVNPayIPN job (idempotent)
         → update order_events
```

**Currency note — VND has no decimals:**
```
-- Store as whole VND (BIGINT still correct, just no cents)
price = 250000  →  250,000 VND  (not cents)

-- VNPay API requires amount × 100:
vnp_Amount = 250000 × 100 = 25000000  (handled inside VNPayDriver, never in app logic)
```

**`PaymentGateway` contract (abstraction layer):**
```php
interface PaymentGateway {
    public function createPaymentUrl(Order $order): string;   // redirect URL
    public function verifyCallback(array $params): bool;       // verify HMAC
    public function refund(Payment $payment, int $amount): bool;
    public function getTransactionStatus(string $ref): string;
}

// Drivers:
Infrastructure/Payment/Drivers/VNPayDriver.php    ← current
Infrastructure/Payment/Drivers/StripeDriver.php   ← add later if needed
```

**Key schema fields on `payments`:**
```
id              ULID PK
order_id        ULID FK → orders
gateway         TEXT NOT NULL DEFAULT 'vnpay'
status          TEXT NOT NULL   -- pending|authorized|captured|failed|refunded
amount          BIGINT NOT NULL -- in VND
currency        CHAR(3) DEFAULT 'VND'
gateway_ref     TEXT NULL       -- vnp_TransactionNo from VNPay
gateway_txn_ref TEXT NULL       -- vnp_TxnRef (your reference sent to VNPay)
raw_response    JSONB NULL      -- full VNPay IPN payload (for debugging)
refunded_at     TIMESTAMPTZ NULL
refund_amount   BIGINT NULL
```

**`payment_events` — append-only (idempotency check):**
```
id              ULID PK
payment_id      ULID FK → payments
event           TEXT        -- 'payment.initiated', 'ipn.received', 'payment.captured' ...
gateway_ref     TEXT NULL   -- check this before processing duplicate IPN
payload         JSONB
created_at      TIMESTAMPTZ
```

---

## 0.6 Customers & Auth

| Decision | Chosen | Notes |
|----------|--------|-------|
| Auth system | **Laravel Sanctum** | Cookie-based for Inertia.js SPA. Token-based ready for future mobile app. No extra setup |
| Social login | **Google via Laravel Socialite** | One package, one provider. Add Facebook/Zalo later — same pattern |
| Customer groups | **No groups** | All customers equal. Add `group_id` column later if VIP/wholesale needed |
| B2B support | **No** | Single seller B2C. No company accounts, no net terms |
| Address book | **Multiple saved addresses** | `user_addresses` table — customers can save home/work/etc. Required for repeat buyers |
| GDPR | **Not required** | Vietnam/Asia market. However: Vietnam **PDPD (Decree 13/2023)** applies — see note below |
| Account deletion | **Anonymize** | Wipe PII, keep order shell. Never hard-delete a user with orders |

**Vietnam PDPD note (Nghị định 13/2023/NĐ-CP):**
```
Vietnam's Personal Data Protection Decree — effective June 2023.
Minimum requirements for your shop:
  ✅ Privacy policy page (what data you collect, why, how long)
  ✅ Consent checkbox at registration + checkout
  ✅ Allow customers to request data correction (via support, not automated)
  ✅ Anonymize on account deletion (already decided above)
Not required yet: automated export endpoint, complex consent logs
```

**Key schema — `users` table:**
```
id                  ULID PK
name                TEXT NOT NULL
email               TEXT NOT NULL UNIQUE
email_verified_at   TIMESTAMPTZ NULL
password            TEXT NULL           -- NULL for social-only accounts
google_id           TEXT NULL           -- Socialite Google ID
avatar_url          TEXT NULL
phone               TEXT NULL
locale              CHAR(5) DEFAULT 'vi'
timezone            TEXT DEFAULT 'Asia/Ho_Chi_Minh'
gdpr_consent_at     TIMESTAMPTZ NULL    -- consent timestamp (PDPD compliance)
anonymized_at       TIMESTAMPTZ NULL    -- set on account deletion
remember_token      TEXT NULL
created_at          TIMESTAMPTZ
updated_at          TIMESTAMPTZ
deleted_at          TIMESTAMPTZ NULL
```

**`user_addresses` table:**
```
id          ULID PK
user_id     ULID FK → users
label       TEXT NULL        -- 'Home', 'Office', 'Parents'
is_default  BOOLEAN DEFAULT false
full_name   TEXT NOT NULL
phone       TEXT NOT NULL
address     TEXT NOT NULL
ward        TEXT NULL        -- phường/xã (Vietnam address)
district    TEXT NULL        -- quận/huyện
city        TEXT NOT NULL    -- tỉnh/thành phố
country     CHAR(2) DEFAULT 'VN'
```

**Anonymize on deletion — what gets wiped:**
```
users: name → 'Deleted User', email → 'deleted_{id}@anon.local',
       phone → null, google_id → null, avatar_url → null
user_addresses: all rows deleted
reviews: author name → 'Deleted User'
orders: preserved as-is (legal + accounting requirement)
```

**Google Socialite flow:**
```
/auth/google → redirect to Google consent screen
/auth/google/callback → find or create user by google_id/email
                      → issue Sanctum session cookie
                      → redirect to dashboard or intended URL
```

---

## 0.7 Promotions & Pricing

| Decision | Chosen | Notes |
|----------|--------|-------|
| Coupon types | **Percentage off** | Start here. Schema uses `value_type` column so fixed/free shipping added later — no migration |
| Coupon stacking | **No stacking** | One coupon per order. Explicit `stackable` flag on coupon for future exceptions |
| Automatic discounts | **Yes — rule-based** | e.g. order ≥ 500,000 VND → 10% off. Conditions stored as JSONB — no code change to add new rules |
| Flash sales | **Yes** | `sale_price` + `sale_starts_at` + `sale_ends_at` on `products` — already in schema (0.2) |
| Loyalty points | **No** | Skip for now. Add `loyalty_ledger` table later if needed |
| Affiliate tracking | **No** | Skip for now. Add `referral_code` on orders + commission table later if needed |

**`coupons` table:**
```
id                  ULID PK
code                TEXT NOT NULL UNIQUE     -- 'SUMMER20'
description         TEXT NULL               -- internal note
value_type          TEXT NOT NULL           -- 'percentage' | 'fixed' | 'free_shipping'
value               BIGINT NOT NULL         -- 20 = 20%, or 50000 = 50,000 VND fixed
min_order_amount    BIGINT NULL             -- minimum cart total to apply (VND)
max_discount_amount BIGINT NULL             -- cap for percentage coupons (VND)
usage_limit         INT NULL                -- total uses allowed, NULL = unlimited
usage_limit_per_user INT NULL               -- per customer limit
used_count          INT NOT NULL DEFAULT 0
stackable           BOOLEAN DEFAULT false
starts_at           TIMESTAMPTZ NULL
expires_at          TIMESTAMPTZ NULL
created_at          TIMESTAMPTZ
deleted_at          TIMESTAMPTZ NULL
```

**`coupon_usage` table:**
```
id          ULID PK
coupon_id   ULID FK → coupons
user_id     ULID NULL FK → users    -- NULL = guest
order_id    ULID FK → orders
discount    BIGINT NOT NULL          -- actual amount discounted (VND)
used_at     TIMESTAMPTZ
```

**`automatic_discounts` table — rule-based engine:**
```
id              ULID PK
name            TEXT NOT NULL        -- 'Order over 500k gets 10% off'
value_type      TEXT NOT NULL        -- 'percentage' | 'fixed'
value           BIGINT NOT NULL
conditions      JSONB NOT NULL       -- rules evaluated at cart calculation
priority        INT DEFAULT 0        -- higher = evaluated first
stackable       BOOLEAN DEFAULT false
starts_at       TIMESTAMPTZ NULL
expires_at      TIMESTAMPTZ NULL
active          BOOLEAN DEFAULT true
```

**Conditions JSONB examples:**
```json
{"min_order_amount": 500000}                          ← order ≥ 500k VND
{"min_order_amount": 1000000, "max_uses": 100}        ← flash sale with cap
{"applies_to_product_ids": ["ulid1","ulid2"]}         ← specific products only
{"applies_to_category_ids": ["ulid3"]}                ← entire category
{"customer_is_new": true}                             ← first order only
```

**Discount calculation order at checkout:**
```
1. Apply automatic discounts (by priority, no stacking unless stackable=true)
2. Apply coupon code (if provided, if not already max discounts)
3. Apply flash sale price (already on product, not a discount — just the price)
4. Calculate shipping
5. grand_total = subtotal - discount_total + shipping_total
```

---

## 0.8 Shipping & Fulfilment

| Decision | Chosen | Notes |
|----------|--------|-------|
| Shipping rates | **Decide later** | Schema supports flat / zone / weight / free threshold — pick in Filament, no code change |
| Carrier integration | **Manual for now** | Admin enters tracking number in Filament. GHN/GHTK API ready to plug in later |
| Tracking | **Manual entry** | `tracking_number` + `carrier` on `shipments`. Customer notified via notification job |
| Click & collect | **No** | Delivery only |
| Dropshipping | **No** | You hold stock and ship yourself |

**`shipping_methods` table — flexible rate engine:**
```
id              ULID PK
name            TEXT NOT NULL        -- 'Standard', 'Express', 'Free Shipping'
carrier         TEXT NULL            -- 'ghn' | 'ghtk' | 'viettelpost' | 'manual'
rate_type       TEXT NOT NULL        -- 'flat' | 'zone' | 'weight' | 'free_threshold'
rate_config     JSONB NOT NULL       -- rules for this method (see below)
min_order       BIGINT NULL          -- minimum order amount to show this method (VND)
free_threshold  BIGINT NULL          -- free if order >= this amount (VND)
is_active       BOOLEAN DEFAULT true
sort_order      SMALLINT DEFAULT 0
```

**`rate_config` JSONB examples (decide later, just insert a row):**
```json
// Flat rate:
{"amount": 30000}

// Zone-based (by city):
{"zones": {"HCM": 20000, "HN": 30000, "default": 45000}}

// Weight-based:
{"per_kg": 10000, "base": 20000, "max_weight_kg": 30}

// Free over threshold (use free_threshold column, rate_config minimal):
{"amount": 0}
```

**`shipments` table:**
```
id                  ULID PK
order_id            ULID FK → orders
shipping_method_id  ULID FK → shipping_methods
carrier             TEXT NULL            -- 'ghn' | 'ghtk' | 'manual'
tracking_number     TEXT NULL            -- entered manually by admin now
carrier_order_id    TEXT NULL            -- carrier's internal ID (for API later)
status              TEXT NOT NULL        -- 'pending'|'picked_up'|'in_transit'|'delivered'|'failed'
estimated_at        TIMESTAMPTZ NULL
delivered_at        TIMESTAMPTZ NULL
shipping_cost       BIGINT NOT NULL      -- actual cost charged (VND)
```

**`shipment_events` table — append-only tracking log:**
```
id              ULID PK
shipment_id     ULID FK → shipments
status          TEXT NOT NULL
description     TEXT NULL            -- 'Package arrived at HCM sorting center'
location        TEXT NULL
happened_at     TIMESTAMPTZ
source          TEXT DEFAULT 'manual' -- 'manual' | 'ghn_webhook' | 'ghtk_webhook'
```

**Manual flow (now):**
```
Order confirmed → admin packs → enters tracking_number in Filament
               → Shipment status = 'picked_up'
               → NotifyCustomerShipped job dispatched
               → Customer gets email with tracking number
```

**API flow (later — just add a driver):**
```
Infrastructure/Carrier/Contracts/CarrierDriver.php
Infrastructure/Carrier/Drivers/GHNDriver.php      ← GHN API
Infrastructure/Carrier/Drivers/GHTKDriver.php     ← GHTK API
Infrastructure/Carrier/Drivers/ManualDriver.php   ← current
```

**Vietnamese carriers — ready to integrate when needed:**

| Carrier | API Quality | Best For |
|---------|------------|----------|
| GHN (Giao Hàng Nhanh) | Good — webhooks supported | Urban, fast delivery |
| GHTK (Giao Hàng Tiết Kiệm) | Good — webhooks supported | Cost-sensitive, wide coverage |
| ViettelPost | Moderate | Rural coverage |

---

## 0.9 Content & SEO

| Decision | Chosen | Notes |
|----------|--------|-------|
| Frontend | **Inertia.js + Vue 3** | Laravel routing + Vue UI. No separate API layer. One codebase |
| SSR | **Yes — Inertia SSR** | Server renders full HTML on first load. Google indexes product/category pages correctly |
| Admin panel | **Filament 3** | Separate from customer-facing frontend. `/admin` route, own auth guard |
| CMS for pages | **DB pages** | `pages` + `page_translations` tables. Editable in Filament — no deploy to update About/Policy pages |
| Blog | **Yes** | Full module — see Section 22. `posts`, categories, tags, related products |
| Image optimization | **Spatie Media Library + Cloudflare Image Resizing** | Upload once → Cloudflare delivers correct size per device via `srcset` |
| Sitemap | **`spatie/laravel-sitemap`** | Queue job regenerates on product/category/post publish. Pings Google on change |
| JSON-LD | **Service class per page type** | `JsonLdBuilder` — Product, Category, Article, Organization, Breadcrumb schemas |

**UI layer:**
```
Tailwind CSS (utility-first)
No component library — build clean, lightweight, shop-specific components
```

**Frontend stack summary:**
```
Laravel 13          ← routing, controllers, data
Inertia.js          ← bridge (no API boilerplate)
Vue 3 (Composition API + <script setup>)
Tailwind CSS
Vite               ← asset bundling (built into Laravel)
@inertiajs/vue3    ← Inertia Vue adapter
```

**SSR setup:**
```
npm install @inertiajs/vue3 @vue/server-renderer
node ace inertia:start-ssr   ← starts Node.js SSR server alongside PHP

Request flow with SSR:
  Browser → Laravel → Inertia renders Vue on Node.js server
          → full HTML returned → browser hydrates → SPA from there on
```

**Spatie Media Library — image collections per model:**
```php
// Product has multiple image collections:
Product::addMediaCollection('featured')      // main product image
        ->singleFile();
Product::addMediaCollection('gallery');      // additional images
Post::addMediaCollection('featured')         // blog featured image
    ->singleFile();

// Conversions auto-generated on upload:
->registerMediaConversions(function() {
    $this->addMediaConversion('thumb')   // 300×300  (listing cards)
         ->addMediaConversion('medium')  // 800×800  (product page)
         ->addMediaConversion('og')      // 1200×630 (social share)
})
```

**Cloudflare Image Resizing — serve via CDN:**
```
Original uploaded to R2:   r2.yourdomain.com/products/abc.jpg
Cloudflare resizes on-fly: cdn.yourdomain.com/cdn-cgi/image/w=800,q=85/products/abc.jpg
srcset in HTML:
  <img srcset="...w=400 400w, ...w=800 800w, ...w=1200 1200w"
       sizes="(max-width: 640px) 400px, 800px">
```

**SEO fields on every public page (auto-resolved):**
```
<title>         → meta_title ?? product/post name + ' | Shop Name'
<meta desc>     → meta_desc ?? excerpt ?? first 160 chars of description
og:image        → featured_image ?? first product image ?? default OG image
og:type         → 'product' | 'article' | 'website'
canonical       → always set — prevents duplicate URL indexing
JSON-LD         → injected per page type (Product, Article, BreadcrumbList...)
```

---

## 0.10 Notifications & Communication

| Decision | Chosen | Notes |
|----------|--------|-------|
| Email provider | **Resend** | Modern API, 3,000 free/month. Laravel driver: `resend/resend-laravel` |
| Email templates | **DB-managed** | `notification_templates` table — editable in Filament, no deploy to fix a typo |
| SMS | **No — not now** | `SmsChannel` interface ready. Add ESMS/SpeedSMS (Vietnam) or Twilio later |
| Push notifications | **No — not now** | Firebase FCM channel ready to add when mobile app is built |
| Notification log | **Yes — DB channel** | Laravel's built-in `notifications` table. Query "unread notifications" in customer account |
| Marketing email | **No — transactional only** | Add Mailchimp/Klaviyo as separate tool if needed — never use transactional provider for campaigns |

**Transactional emails to build (priority order):**
```
1. order.confirmed          → customer gets order summary + items + total
2. order.shipped            → tracking number + carrier + estimated delivery
3. order.delivered          → thank you + review request link
4. payment.failed           → payment failed, retry link
5. auth.email_verification  → verify email on registration
6. auth.password_reset      → reset password link
7. auth.welcome             → welcome after first registration
8. order.cancelled          → cancellation confirmation + refund info
9. return.confirmed         → return request received
10. low_stock_alert         → admin alert when product hits low_stock_alert threshold
```

**`notification_templates` table:**
```
id          ULID PK
key         TEXT NOT NULL UNIQUE    -- 'order.confirmed', 'order.shipped' ...
channel     TEXT NOT NULL           -- 'mail' | 'sms' | 'push'
subject     TEXT NULL               -- email subject line
body        TEXT NOT NULL           -- HTML body (TinyMCE in Filament)
variables   JSONB NOT NULL          -- {"order_number":"#1234","total":"250,000 VND",...}
is_active   BOOLEAN DEFAULT true
updated_at  TIMESTAMPTZ
```

**How it works — render at send time:**
```php
// NotificationTemplateService resolves template by key, replaces variables:
$template = NotificationTemplate::findByKey('order.confirmed');
$body = $template->render([
    'customer_name' => $order->user->name,
    'order_number'  => $order->id,
    'items'         => $order->items,
    'total'         => money($order->grand_total),
]);
// → dispatched to queue: default
```

**Resend setup in Laravel:**
```
composer require resend/resend-laravel

# .env
MAIL_MAILER=resend
RESEND_KEY=re_xxxxxxxxxxxx
MAIL_FROM_ADDRESS=orders@yourdomain.com
MAIL_FROM_NAME="Shop Name"
```

**Notification channel architecture — ready to extend:**
```php
// Each notification uses channels array:
public function via($notifiable): array {
    return ['mail', 'database'];     // now
    // return ['mail', 'database', 'vonage'];  // when SMS added
    // return ['mail', 'database', 'fcm'];     // when push added
}
```

**Laravel `notifications` table (built-in DB channel):**
```
id           UUID PK
type         TEXT          -- 'App\Notifications\OrderConfirmed'
notifiable   morphs        -- user_id + user_type
data         JSON          -- notification payload
read_at      TIMESTAMPTZ NULL
created_at   TIMESTAMPTZ
```
Used for: "You have 3 unread notifications" in customer account page.

---

## 0.11 Search

| Decision | Chosen | Notes |
|----------|--------|-------|
| Search engine | **Meilisearch — self-hosted** | Runs on same VPS. ~100MB RAM. Free. `meilisearch/meilisearch-laravel-scout` driver |
| Laravel Scout | **Yes** | `Product::search('query')` — swap engine later without touching controllers |
| Faceted filtering | **Yes** | Price range, category, attributes — Meilisearch filters, not SQL WHERE clauses |
| Autocomplete | **Yes** | Vue component hits Meilisearch directly via HTTP. No Laravel round-trip needed |
| Search analytics | **Yes** | `search_logs` table — query + result_count + user_id. Zero-result = product gap |

**What gets indexed in Meilisearch:**

| Index | Fields | Used For |
|-------|--------|----------|
| `products` | name, description (stripped), sku, category, tags, attributes JSONB, price, sale_price, in_stock | Product search + facets |
| `posts` | title, excerpt, body (stripped), category, tags | Blog search |
| `pages` | title, body (stripped) | Static page search (FAQ etc.) |

**Meilisearch index settings for `products`:**
```json
{
  "searchableAttributes": ["name", "description", "sku", "tags", "category_name"],
  "filterableAttributes": ["category_id", "price", "sale_price", "in_stock", "attributes"],
  "sortableAttributes": ["price", "created_at", "name"],
  "rankingRules": ["words", "typo", "proximity", "attribute", "sort", "exactness"],
  "typoTolerance": { "enabled": true, "minWordSizeForTypos": { "oneTypo": 4 } }
}
```

**Faceted filter examples (Meilisearch query):**
```php
Product::search($query)
    ->options([
        'filter' => 'category_id = "ulid123" AND price 100000 TO 500000 AND in_stock = true',
        'sort'   => ['price:asc'],
        'facets' => ['category_id', 'attributes.color', 'attributes.size'],
    ])
    ->paginate(24);
```

**Autocomplete — Vue hits Meilisearch directly:**
```
User types "áo th..." → Vue debounce 300ms
  → POST https://search.yourdomain.com/indexes/products/search
  → { q: "áo th", limit: 8, attributesToRetrieve: ["name","slug","featured_image","price"] }
  → dropdown renders in <50ms
```
Note: expose Meilisearch via subdomain with API key scoped to search-only (read-only key, no write access from frontend).

**`search_logs` table:**
```
id              ULID PK
query           TEXT NOT NULL
results_count   INT NOT NULL
user_id         ULID NULL FK → users   -- NULL = guest
session_id      TEXT NULL
filters_used    JSONB NULL             -- {"category":"shirts","price":"100k-500k"}
created_at      TIMESTAMPTZ
```

**Zero-result monitoring:**
```sql
-- Run weekly in Filament report or analytics dashboard:
SELECT query, COUNT(*) as searches
FROM search_logs
WHERE results_count = 0
GROUP BY query
ORDER BY searches DESC
LIMIT 20;
-- → These are products you should add or synonyms you should configure
```

**Sync strategy — keep index fresh:**
```
ProductCreated  → SyncProductToSearch job  (queue: search-sync)
ProductUpdated  → SyncProductToSearch job  (queue: search-sync)
ProductDeleted  → RemoveProductFromSearch job
StockDepleted   → update in_stock = false in index (queue: search-sync)
StockRestored   → update in_stock = true  in index (queue: search-sync)
```

**Vietnamese language — Meilisearch config:**
```json
// Meilisearch handles Vietnamese well with default tokenizer
// Add common Vietnamese synonyms:
{
  "synonyms": {
    "áo thun": ["áo phông", "t-shirt"],
    "quần jean": ["quần bò", "jeans"],
    "giày": ["dép", "sandal"]
  }
}
```

---

## 0.12 Infrastructure & Deployment

| Decision | Chosen | Notes |
|----------|--------|-------|
| Hosting | **Local VPS (Vietnam) + Laravel Forge** | Low latency for VN users. Forge manages server regardless of provider. Ubuntu 22.04 LTS |
| Server size | **2 vCPU / 4GB RAM** | Runs all services comfortably at launch. Scale up before K8s |
| Containerization | **Docker Compose (dev only)** | No Docker in production — Forge manages processes directly. Simpler ops |
| PHP runtime | **FrankenPHP + Octane** | Persistent PHP workers. No bootstrap cost per request. Already decided in Stack |
| CI/CD | **GitHub Actions + Forge webhook** | Push to `main` → tests pass → Forge deploys. Zero-downtime with Octane reload |
| Secrets | **Forge environment manager** | Forge encrypts and manages `.env` on server. No secrets in Git ever |
| CDN | **Cloudflare (free tier)** | Cloudflare has PoPs in HCM + Hanoi. Vietnamese users get edge-cached assets locally |
| Object storage | **Cloudflare R2** | Zero egress fees. S3-compatible. Served via Cloudflare CDN — same network, fast |
| Multi-region | **No — single VN region** | Start single server. Stateless app means adding a second server later is just Forge + load balancer |
| Queue driver | **Redis** | Same server. Horizon manages workers. Upgrade to SQS if moving to multi-server |

---

**Vietnam VPS providers — Forge-compatible (Ubuntu 22.04):**

| Provider | Notes |
|----------|-------|
| **Viettel IDC** | Best network coverage VN, enterprise grade |
| **FPT Cloud** | Good performance, HCMC + HN data centers |
| **CMC Telecom** | Competitive pricing, good connectivity |
| **Singapore fallback** | DO/Vultr Singapore is ~30ms from VN — better uptime SLA if local provider unreliable |

> Forge works with **any** provider via "Custom VPS" option — just needs SSH access to a fresh Ubuntu 22.04 server.

---

**Single server RAM allocation (4GB total):**
```
PostgreSQL 16       ~800MB   (shared_buffers = 1GB → adjust down to 512MB)
Redis               ~200MB
Meilisearch         ~300MB
FrankenPHP/Octane   ~600MB   (workers × memory per worker)
Horizon workers     ~300MB   (queue workers)
OS + misc           ~400MB
PgBouncer           ~30MB
──────────────────────────
Total               ~2.6GB   ← comfortable on 4GB, leaves headroom
```

**When to upgrade (signals):**
```
RAM usage > 80% consistently     → upgrade to 8GB RAM
CPU > 70% sustained              → add second app server
Queue depth > 500 consistently   → scale Horizon workers
DB connections > 80 via PgBouncer → increase pool size or move DB to separate server
```

---

**GitHub Actions deploy pipeline:**
```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [main]

jobs:
  test:
    - composer install
    - php artisan test          # Pest — must pass before deploy

  deploy:
    needs: test
    - curl Forge deploy webhook  # triggers Forge zero-downtime deploy
```

**Forge deploy script (zero-downtime with Octane):**
```bash
cd /home/forge/site
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force       # backward-compatible only
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart         # graceful Horizon restart
php artisan octane:reload         # reload workers without downtime
npm ci && npm run build           # Vite assets
```

**Cloudflare R2 + Laravel Filesystem:**
```
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=r2_key
AWS_SECRET_ACCESS_KEY=r2_secret
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-bucket
AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
AWS_URL=https://cdn.yourdomain.com   ← custom domain via Cloudflare
```

**Local dev Docker Compose services:**
```yaml
services:
  app:        php:8.5 + FrankenPHP
  postgres:   postgres:16
  redis:      redis:7-alpine
  meilisearch: getmeili/meilisearch:latest
  mailpit:    axllent/mailpit         ← catches all dev emails
```

---

## 0.13 Observability & Quality

| Decision | Chosen | Notes |
|----------|--------|-------|
| Error tracking | **None for now** | ⚠️ Add **Flare** before going live — silent errors in production = lost orders. Free tier sufficient |
| APM / Monitoring | **Laravel Pulse** | Free, built-in. Real-time dashboard at `/pulse`. Tracks requests, queues, cache, slow queries |
| Log aggregation | **Laravel log files** | Logs on server. Know where: `/storage/logs/laravel.log`. Rotate daily |
| Uptime monitoring | **BetterUptime** | Free: 10 monitors, 3min checks, public status page, Slack/email alerts |
| Test coverage | **Pest + Feature tests** | Happy paths + critical flows. Run in GitHub Actions before every deploy |
| Code style | **Laravel Pint** | Run in CI — `./vendor/bin/pint --test`. No formatting debates |

---

**⚠️ Strong recommendation — add Flare before going live:**
```
Silent PHP exceptions in production = customers see 500 errors with no trace
Lost payments, broken checkout, failed jobs — you'll never know without error tracking

Flare setup (15 minutes):
  composer require spatie/laravel-flare
  FLARE_KEY=your_key in .env
  Done. Every exception emailed + logged with full Laravel context.

Free tier: 300 errors/day — more than enough for a growing shop.
```

**Laravel Pulse — what it shows:**
```
/pulse dashboard (admin-only route):
  ├── Requests        → slowest endpoints, error rate
  ├── Queues          → job throughput, failed jobs, wait time
  ├── Cache           → hit/miss ratio
  ├── Slow queries    → queries > 100ms with full SQL
  ├── Slow jobs       → jobs taking too long
  ├── Exceptions      → recent errors (basic — Flare is better for this)
  └── Servers         → CPU, RAM, disk per server
```

**Pulse setup:**
```bash
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"
php artisan migrate
# Add to routes/web.php (admin guard):
Route::middleware(['auth', 'can:viewPulse'])->group(function () {
    Route::get('/pulse', \Laravel\Pulse\Http\Controllers\DashboardController::class);
});
```

**Log files — structure and access:**
```bash
# Location on server:
/home/forge/yoursite/storage/logs/laravel.log

# Rotate daily (Forge logrotate or Laravel config):
# config/logging.php → 'daily' channel, 14 days retention

# Read latest errors quickly:
tail -f /home/forge/yoursite/storage/logs/laravel.log
grep "ERROR" storage/logs/laravel.log | tail -50
```

**BetterUptime monitors to set up:**
```
1. https://yourdomain.com               → main site
2. https://yourdomain.com/api/health    → app health endpoint (returns 200)
3. https://yourdomain.com/admin         → admin panel
Alert channels: email + Slack/Telegram
Status page: status.yourdomain.com (free subdomain via BetterUptime)
```

**Health check endpoint (add to routes):**
```php
// Returns 200 if app + DB + Redis + queue are healthy
Route::get('/api/health', function () {
    return response()->json([
        'status' => 'ok',
        'db'     => DB::connection()->getPdo() ? 'ok' : 'fail',
        'redis'  => Cache::store('redis')->get('health') !== null ? 'ok' : 'fail',
        'queue'  => 'ok', // Horizon status check
    ]);
});
```

**Pest — critical tests to write first:**
```
tests/Feature/
  ├── Checkout/
  │   ├── GuestCanCheckoutTest.php       ← most critical
  │   ├── StockReservedOnCheckoutTest.php
  │   └── PaymentFailedReleasesStockTest.php
  ├── Products/
  │   ├── ProductListingTest.php
  │   └── ProductSearchTest.php
  ├── Auth/
  │   ├── LoginTest.php
  │   └── GoogleSocialiteTest.php
  └── Orders/
      ├── OrderStateTransitionTest.php
      └── VNPayWebhookIdempotentTest.php  ← payment webhook must be tested
```

**GitHub Actions — quality gates:**
```yaml
jobs:
  quality:
    steps:
      - run: ./vendor/bin/pint --test       # fail if code not formatted
      - run: php artisan test               # fail if any test fails
      - run: composer audit                 # fail if known CVE in dependencies
  deploy:
    needs: quality                          # deploy only if all pass
```

---

## 0.14 Security & Compliance

| Decision | Chosen | Notes |
|----------|--------|-------|
| WAF | **Cloudflare WAF** | Free tier. Blocks SQLi, XSS, bad bots at edge before hitting server |
| Rate limiting | **Both — Cloudflare + Laravel** | Cloudflare at edge (IP-level), Laravel `throttle` on routes (user-level) |
| DDoS protection | **Cloudflare** | Included free in all Cloudflare plans |
| PCI compliance | **Not required** | VNPay redirect flow — card data never touches your server. VNPay is PCI-DSS certified |
| GDPR | **Not required** | Vietnam/Asia market. PDPD 2023 covered by `gdpr_consent_at` column (see 0.6) |
| Audit log | **Yes — admin actions only** | `spatie/laravel-activitylog` on admin models. Who changed what, when |
| 2FA | **No** | ⚠️ Strongly recommended to reconsider — one compromised admin password = full system access |
| API auth | **Sanctum session** | Cookie-based for Inertia.js SPA. No bearer tokens needed — internal only |
| Dependency audit | **`composer audit` in CI** | Already in GitHub Actions quality gate (see 0.13) |

---

**⚠️ 2FA note — reconsider before launch:**
```
Your admin panel (Filament) has access to:
  → All customer data (names, emails, addresses)
  → All orders and payment references
  → Ability to issue refunds
  → Product/pricing changes

One stolen admin password = full breach. Filament 2FA takes 10 minutes:
  composer require filament/filament
  // 2FA is built-in — enable in FilamentServiceProvider
  $panel->twoFactorAuthentication()

Minimum: enable for your own account. Cost: zero.
```

---

**Brute force / credential stuffing protection:**

```php
// 1. Laravel built-in login throttle (already in Fortify/Sanctum):
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->email),        // 5 attempts per email
        Limit::perMinute(10)->by($request->ip()),         // 10 attempts per IP
    ];
});

// 2. Lock account after N failures:
// Use spatie/laravel-login-link or custom:
if ($user->failed_login_count >= 10) {
    $user->locked_until = now()->addHours(1);
}

// 3. Cloudflare — rate limit /login route at edge:
// Cloudflare Dashboard → Security → WAF → Rate Limiting:
// URL: /login  Method: POST  Threshold: 5/min per IP → Block 1hr
```

**Add to `users` table:**
```
failed_login_count  INT NOT NULL DEFAULT 0
locked_until        TIMESTAMPTZ NULL
last_login_at       TIMESTAMPTZ NULL
last_login_ip       TEXT NULL
```

**Cloudflare Turnstile — bot-proof login (no annoying CAPTCHA):**
```
Replaces hCaptcha/reCAPTCHA. Invisible to real users, blocks bots.
Free. Add to: login form, registration form, checkout (guest email step).
Laravel package: coderflex/laravel-turnstile
```

**VNPay HMAC verification — non-negotiable:**
```php
// Even though you didn't flag it — this is critical.
// A fake IPN callback = attacker marks order as paid without paying.

// In VNPayDriver::verifyCallback():
$vnpSecureHash = $params['vnp_SecureHash'];
unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
ksort($params);
$hashData = http_build_query($params);
$expectedHash = hash_hmac('sha512', $hashData, config('vnpay.hash_secret'));

if (!hash_equals($expectedHash, $vnpSecureHash)) {
    // Reject. Log. Alert.
    throw new InvalidVNPaySignatureException();
}
```

**Rate limits per route group:**
```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {    // 60/min general API
    Route::post('/checkout', ...);
});

Route::middleware('throttle:5,1')->group(function () {     // 5/min sensitive
    Route::post('/auth/login', ...);
    Route::post('/auth/register', ...);
    Route::post('/vnpay/ipn', ...);                        // IPN endpoint
});

Route::middleware('throttle:30,1')->group(function () {    // 30/min search
    Route::get('/search', ...);
});
```

**Security headers (add to Nginx / Caddy config):**
```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: default-src 'self'; ...
```

**`spatie/laravel-activitylog` — what to log:**
```php
// Auto-log on these admin models:
Product::class    → created, updated, deleted
Order::class      → status changed, notes updated
Coupon::class     → created, updated, deleted
User::class       → role changed, account locked
Payment::class    → refund issued

// Stored in activity_log table:
// causer_id (admin user), subject_type, subject_id, event, properties (before/after)
```

**Pre-launch security checklist:**
```
✅ Cloudflare proxying enabled (orange cloud)
✅ SSL/HTTPS enforced (Forge auto-provisions Let's Encrypt)
✅ /admin not indexed by Google (robots.txt Disallow: /admin/)
✅ .env not publicly accessible
✅ APP_DEBUG=false in production
✅ APP_ENV=production
✅ VNPay HMAC verified on every IPN
✅ composer audit passing in CI
✅ Rate limiting on login + checkout + IPN
✅ Cloudflare Turnstile on login + register forms
```

---

## 0.15 Scalability Rules — Non-Negotiable

> Not decisions. Rules. Follow from day one regardless of current traffic.

---

**✅ Rule 1 — Stateless application layer**
```
Sessions   → Redis (never file-based)
Cache      → Redis (never local array cache in production)
Uploads    → Cloudflare R2 (never server local disk)

Why: any server must be able to handle any request.
     When you add server #2, it shares Redis + R2 — zero reconfiguration.

config/session.php  → 'driver' => 'redis'
config/cache.php    → 'default' => 'redis'
config/filesystems  → 'default' => 'r2'
```

**✅ Rule 2 — All slow work goes to a queue**
```
Goes to queue (never block HTTP response):
  ✓ Order confirmation email          → queue: default
  ✓ Shipping notification email       → queue: default
  ✓ VNPay IPN processing              → queue: critical
  ✓ Stock deduction after payment     → queue: critical
  ✓ Meilisearch index sync            → queue: search-sync
  ✓ Image conversion (Spatie Media)   → queue: default
  ✓ Sitemap regeneration              → queue: cleanup
  ✓ Invoice PDF generation            → queue: default
  ✓ Search log recording              → queue: reporting
  ✓ Activity log write                → queue: reporting

Target: HTTP response < 200ms for all user-facing routes.
```

**✅ Rule 3 — Idempotent queue jobs**
```
Every job must be safe to run twice — queues guarantee at-least-once delivery.

VNPay IPN job:
  → Check payment_events for existing gateway_ref before processing
  → If found: skip silently, return success to VNPay
  → If not found: process and insert into payment_events

Stock deduction job:
  → Use UPDATE with WHERE qty_reserved >= qty (atomic, not read-then-write)
  → If 0 rows affected: already deducted, skip

Email job:
  → Check notification_log for existing order_id + template_key
  → If found: skip (customer already received this email)
```

**✅ Rule 4 — Feature flags via `laravel/pennant`**
```
Deploy code OFF, enable per user or % rollout without a redeploy.

Use for:
  → New checkout flow (test on 10% of users first)
  → New product page layout
  → Flash sale feature (enable/disable instantly)
  → Any risky new feature

Feature::define('new-checkout', fn (User $user) =>
    $user->created_at->isAfter(now()->subDays(30))  // new users only
);

// In controller:
if (Feature::active('new-checkout')) {
    return inertia('Checkout/NewCheckout');
}
return inertia('Checkout/Checkout');
```

**✅ Rule 5 — Event-driven module communication**
```
Modules NEVER call each other directly.
All cross-module side effects go through Laravel Events.

✗ Wrong:
  class OrderController {
      public function store() {
          $order = Order::create(...);
          app(EmailService::class)->sendConfirmation($order);   // direct call
          app(InventoryService::class)->deductStock($order);    // direct call
      }
  }

✓ Correct:
  class OrderController {
      public function store() {
          $order = Order::create(...);
          OrderPlaced::dispatch($order);   // one line — all listeners fire via queue
      }
  }

Why: adding a new side effect (loyalty points, analytics) = add one Listener.
     Zero changes to OrderController.
```

**✅ Rule 6 — Order snapshot immutability**
```
At checkout — copy into order_items:
  ✓ product_name    (product may be renamed later)
  ✓ product_sku     (SKU may change)
  ✓ unit_price      (price may change tomorrow)
  ✓ featured_image_url  (image may be replaced)

Never do this in order history view:
  ✗ $item->product->name      ← product may be deleted
  ✗ $item->product->price     ← price may have changed
  ✓ $item->product_name       ← always the price they paid
  ✓ $item->unit_price         ← always the price they paid
```

**✅ Rule 7 — Backward-compatible migrations only**
```
Zero-downtime deploy: old code and new code run simultaneously during deploy.
Schema changes must work with BOTH versions.

✗ Dangerous (breaks old code instantly):
  $table->renameColumn('price', 'amount');
  $table->dropColumn('status');
  $table->string('email')->nullable(false)->change(); // add NOT NULL to existing

✓ Safe — 3-deploy cycle:
  Deploy 1: $table->bigInteger('amount')->nullable()->after('price');
  Deploy 2: backfill job → UPDATE products SET amount = price
  Deploy 3: $table->bigInteger('amount')->nullable(false)->change();
            + $table->dropColumn('price'); (old column)
```

**✅ Rule 8 — Secrets never in code or Git**
```
✗ Never:
  define('VNPAY_SECRET', 'abc123');            // in code
  git add .env                                  // in Git

✓ Always:
  .env → managed by Forge (encrypted, per-environment)
  .gitignore → .env is listed (Laravel default)
  Rotate VNPay secret → update in Forge env panel, reload Octane workers
  No redeploy needed.
```

**✅ Rule 9 — UTC everywhere, format in UI**
```
Store:   created_at TIMESTAMPTZ = 2026-04-06 10:30:00+00  (UTC)
Display: "06/04/2026 17:30"  (UTC+7 Vietnam time, formatted in Vue)

// In Vue component:
import { useDateFormat } from '@vueuse/core'
const localTime = useDateFormat(order.created_at, 'DD/MM/YYYY HH:mm', { locales: 'vi-VN' })

Never store: "2026-04-06 17:30:00" (local time — breaks on server timezone change)
```

---

## 0.16 Package Baseline — Your Final Stack

> Tailored to your decisions. Every package has a confirmed reason.

### Core Framework
| Package | Version | Why |
|---------|---------|-----|
| `laravel/framework` | 13.x | Foundation |
| `laravel/octane` | latest | Persistent workers via FrankenPHP |
| `laravel/horizon` | latest | Queue dashboard + worker management |
| `laravel/sanctum` | latest | Session auth for Inertia SPA |
| `laravel/scout` | latest | Meilisearch abstraction |
| `laravel/pennant` | latest | Feature flags (Rule 4) |
| `laravel/pint` | latest | Code style enforcement in CI |

### Frontend
| Package | Version | Why |
|---------|---------|-----|
| `inertiajs/inertia-laravel` | latest | Inertia server adapter |
| `@inertiajs/vue3` | latest | Inertia Vue 3 client |
| `vue` | 3.x | Frontend framework |
| `@vitejs/plugin-vue` | latest | Vite + Vue integration |
| `tailwindcss` | 3.x | Utility CSS |
| `@vueuse/core` | latest | Vue composables (dates, debounce, etc.) |

### Admin
| Package | Version | Why |
|---------|---------|-----|
| `filament/filament` | 3.x | Admin panel — saves months |
| `awcodes/filament-tiptap-editor` | latest | Rich text (TinyMCE replacement) in Filament |

### Database & Models
| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-translatable` | latest | JSONB translations — 0.1 decision |
| `lazychaser/laravel-nestedset` | latest | Category tree — 0.2 decision |
| `moneyphp/money` | latest | BIGINT cents arithmetic — 0.1 decision |

### Media & Storage
| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-medialibrary` | latest | Image upload + conversions — 0.9 decision |
| `league/flysystem-aws-s3-v3` | latest | R2 (S3-compatible) filesystem driver |

### SEO & Content
| Package | Version | Why |
|---------|---------|-----|
| `spatie/laravel-sitemap` | latest | Sitemap generation — 0.9 decision |

### Search
| Package | Version | Why |
|---------|---------|-----|
| `meilisearch/meilisearch-laravel-scout` | latest | Meilisearch Scout driver — 0.11 decision |

### Auth & Security
| Package | Version | Why |
|---------|---------|-----|
| `laravel/socialite` | latest | Google login — 0.6 decision |
| `coderflex/laravel-turnstile` | latest | Cloudflare Turnstile bot protection — 0.14 decision |
| `stevebauman/purify` | latest | XSS sanitize TinyMCE HTML before saving — 0.2 decision |

### Notifications & Email
| Package | Version | Why |
|---------|---------|-----|
| `resend/resend-laravel` | latest | Resend email driver — 0.10 decision |

### Observability & Quality
| Package | Version | Why |
|---------|---------|-----|
| `laravel/pulse` | latest | Production monitoring dashboard — 0.13 decision |
| `laravel/telescope` | latest | Dev/staging debug — 0.13 decision |
| `spatie/laravel-activitylog` | latest | Admin audit log — 0.14 decision |
| `spatie/laravel-health` | latest | `/api/health` endpoint — 0.13 decision |
| `pestphp/pest` | latest | Testing — 0.13 decision |
| `pestphp/pest-plugin-laravel` | latest | Laravel Pest helpers |

### Payments
| Package | Version | Why |
|---------|---------|-----|
| *(custom)* `VNPayDriver.php` | — | No official package — build clean driver behind `PaymentGateway` contract |

### Dev Tools (require-dev only)
| Package | Version | Why |
|---------|---------|-----|
| `fakerphp/faker` | latest | Test data seeding |
| `laravel/pint` | latest | Code formatting |
| `enlightn/enlightn` | latest | Security audit scan |

---

**Install order (phases):**
```
Phase 1 — Foundation:
  laravel/octane, laravel/horizon, laravel/sanctum,
  filament/filament, laravel/pint, pestphp/pest

Phase 2 — Data layer:
  spatie/laravel-translatable, lazychaser/laravel-nestedset,
  moneyphp/money, spatie/laravel-medialibrary, league/flysystem-aws-s3-v3

Phase 3 — Features:
  laravel/scout + meilisearch driver, laravel/socialite,
  spatie/laravel-sitemap, resend/resend-laravel, laravel/pennant

Phase 4 — Quality & Security:
  laravel/pulse, laravel/telescope, spatie/laravel-activitylog,
  spatie/laravel-health, coderflex/laravel-turnstile,
  stevebauman/purify, enlightn/enlightn
```

---

## Dev ↔ Production Parity

- Local: Laragon + PostgreSQL 16 (manual binary) OR `postgres:16` in Docker Compose
- Production: Forge VPS + PostgreSQL 16 (auto) + PgBouncer
- Both must run **PostgreSQL 16** — never mix major versions
