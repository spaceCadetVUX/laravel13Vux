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

## Dev ↔ Production Parity

- Local: Laragon + PostgreSQL 16 (manual binary) OR `postgres:16` in Docker Compose
- Production: Forge VPS + PostgreSQL 16 (auto) + PgBouncer
- Both must run **PostgreSQL 16** — never mix major versions
