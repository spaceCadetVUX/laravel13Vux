# Laravel 13 Shop — Infrastructure Blueprint

> **Stack:** Laravel 13 · PHP 8.5 · Designed for horizontal scale & easy future upgrades

---

## 0. Before You Build — Pre-Flight Checklist

> These are **architectural decisions** — changing them later costs weeks, not hours.
> Work through every section. Each item has a **recommended default** in `« »` if you have no strong reason to choose otherwise.

---

### 0.1 Database Fundamentals

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 1 | Engine | PostgreSQL 16 / MySQL 8 | **PostgreSQL** — better locks, JSON, partial indexes, UUID native |
| 2 | ID strategy | Auto-increment INT / UUID v4 / ULID | **ULID** — sortable like INT, unique like UUID, URL-safe |
| 3 | Money storage | FLOAT / DECIMAL / BIGINT cents | **BIGINT cents** — never float, never decimal |
| 4 | Timestamps | Local TZ / UTC | **UTC always**, convert in UI layer |
| 5 | Soft deletes | Hard delete / `deleted_at` | **`deleted_at` on all business tables** |
| 6 | Translations | JSON files / DB `*_translations` tables | **DB tables** — editable at runtime, no deploy needed |
| 7 | Connection pooling | None / PgBouncer / ProxySQL | **PgBouncer** (transaction mode) — mandatory at scale |
| 8 | Multi-tenancy | Single DB / schema-per-tenant / DB-per-tenant | **Single DB** to start, schema-per-tenant if B2B SaaS later |

---

### 0.2 Product Catalog Design

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 9 | Product types | Simple only / + variants / + bundles / + digital | Decide now — variants affect every schema table |
| 10 | Attribute model | Hardcoded columns / EAV rows / JSON column | **JSON column + Meilisearch** — avoids EAV hell, still filterable |
| 11 | Category model | Flat list / Parent-child / Nested Set / Closure Table | **Nested Set** (via `lazychaser/laravel-nestedset`) — fast tree reads |
| 12 | SKU ownership | Per variant / Per product | **Per variant** — each size/color = its own SKU, price, stock |
| 13 | Pricing model | Single price / Price lists / Customer group prices | **Price lists** from day one — B2B, promo, geo prices all use same model |
| 14 | Digital products | No / Yes | If yes: secure download tokens, expiry, download limits in schema |
| 15 | Bundles/kits | No / Yes | If yes: separate `bundle_items` table + composite stock logic |
| 16 | Subscriptions | No / Yes | If yes: integrate Stripe Billing early — schema is very different |

---

### 0.3 Inventory

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 17 | Stock model | Per product / Per variant / Per variant+warehouse | **Per variant** minimum; per variant+warehouse if multi-location |
| 18 | Multi-warehouse | No / Yes | Decide now — deeply affects stock reservation logic |
| 19 | Reservation model | Deduct on order / Reserve on add-to-cart / Reserve on checkout | **Reserve on checkout confirm** + release if payment fails |
| 20 | Oversell policy | Never allow / Allow with backorder flag | **Backorder flag per variant** — some products ok to oversell |
| 21 | Flash sale locking | DB row lock / Optimistic lock / Redis atomic decrement | **Redis atomic** for hot items, DB lock for normal flow |
| 22 | Stock sync | Manual / Webhook from ERP / Polling | Define early if connecting to external WMS/ERP |

---

### 0.4 Orders & Checkout

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 23 | Guest checkout | No / Yes | **Yes** — requiring account kills conversion |
| 24 | Cart storage | Session / DB / Redis | **DB** (with `session_id` for guests, `user_id` for logged in) |
| 25 | Cart merge | No / Yes (merge guest→user on login) | **Yes** — standard expectation |
| 26 | Order state source | `status` column / append-only event log | **Append-only `order_events`** — full audit, replayable |
| 27 | Order immutability | Mutable / Immutable snapshot | **Immutable** — snapshot price/name at order time, never join live product |
| 28 | Multi-vendor | No / Yes (marketplace) | Decide now — marketplace = `seller_id` on every order item, separate payouts |
| 29 | Returns/RMA | No / Simple flag / Full RMA flow | Plan the state machine now even if building later |
| 30 | Tax engine | Manual rate table / TaxJar / Avalara / Stripe Tax | **Stripe Tax** if using Stripe — zero maintenance |

---

### 0.5 Payments

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 31 | Primary gateway | Stripe / PayPal / Braintree / local | **Stripe** — best API, webhooks, fraud tools, subscriptions |
| 32 | Gateway abstraction | Direct Stripe calls / Abstraction layer | **Abstraction layer** (`app/Contracts/PaymentGateway`) — swap gateways without rewrite |
| 33 | Payment storage | Store card? / Tokenize only | **Tokenize only** — never store raw card data, use Stripe Customer ID |
| 34 | Webhook handling | Synchronous / Idempotent queue-based | **Idempotent queue-based** — Stripe may send same event multiple times |
| 35 | Refunds | Manual / Automated rules | Design refund flow and `payment_events` log from day one |
| 36 | Multi-currency payments | No / Yes | If yes: store `amount`, `currency`, `amount_in_base_currency`, `fx_rate` |
| 37 | Payment methods | Card only / + Wallets / + BNPL | Add Apple Pay / Google Pay via Stripe Elements for free |

---

### 0.6 Customers & Auth

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 38 | Auth system | Sanctum / Jetstream / Fortify / Custom | **Sanctum** (API tokens) or **Jetstream** (full-stack) |
| 39 | Social login | No / Yes (Google, Facebook) | **Yes via Laravel Socialite** — reduces friction |
| 40 | Customer groups | No / Yes (retail, wholesale, VIP) | **Yes** — price lists, discounts, shipping rules all use groups |
| 41 | B2B support | No / Yes (company accounts, net terms) | Decide now — B2B adds company→user relationships, credit limits |
| 42 | Address book | Single address / Multiple saved addresses | **Multiple** — standard expectation for repeat buyers |
| 43 | GDPR / Privacy | Not needed / Required | If EU customers: consent log, data export endpoint, right-to-delete job |
| 44 | Account deletion | Hard delete / Anonymize | **Anonymize** — preserve order history, wipe PII |

---

### 0.7 Promotions & Pricing

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 45 | Coupon types | Fixed / Percentage / Free shipping / BOGO | Design a rule-based engine, not hardcoded types |
| 46 | Coupon stacking | No / Yes (max N coupons) | **No stacking** by default — explicit allow-list |
| 47 | Automatic discounts | No / Yes (rule-based cart conditions) | **Yes** — needed for flash sales, bundles, loyalty |
| 48 | Flash sales | No / Yes | If yes: `sale_price` + `sale_starts_at` / `sale_ends_at` on variant |
| 49 | Loyalty points | No / Yes | If yes: separate `loyalty_ledger` append-only table |
| 50 | Affiliate tracking | No / Yes | If yes: `referral_code` on order, commission ledger table |

---

### 0.8 Shipping & Fulfilment

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 51 | Shipping rates | Flat rate / Weight-based / Zone-based / Carrier API | **Zone-based** as default, carrier API for live rates |
| 52 | Carrier integration | None / EasyPost / Shippo / ShipStation | **EasyPost** — multi-carrier, label generation, tracking webhooks |
| 53 | Tracking | Manual entry / Carrier webhook | Carrier webhook → `shipment_events` log → customer notification |
| 54 | Click & collect | No / Yes | Affects warehouse + order flow |
| 55 | Dropshipping | No / Yes | If yes: supplier order routing, separate fulfilment status per item |

---

### 0.9 Content & SEO

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 56 | Frontend | Blade / Inertia.js (Vue/React) / Separate SPA / Mobile app | **Inertia.js** — keeps Laravel routing, no API duplication, SSR-capable |
| 57 | SSR | No / Laravel + Inertia SSR | **Inertia SSR** — critical for SEO on dynamic pages |
| 58 | Admin panel | Custom / Filament 3 | **Filament 3** — saves months, fully customizable |
| 59 | CMS for pages | Hardcoded / DB pages / External CMS | **DB pages** (`pages` + `page_translations`) — team editable, no deploy |
| 60 | Blog | No / Yes | If yes: `posts` table with author, tags, SEO fields from day one |
| 61 | Image optimization | Raw upload / Spatie Media Library | **Spatie Media Library** + Cloudflare Image Resizing for srcset |
| 62 | Sitemap generation | Manual / `spatie/laravel-sitemap` | **spatie/laravel-sitemap** + queue job on content change |
| 63 | JSON-LD | Manual / Service class per page type | **Service class** — `JsonLdBuilder` per page type (see Section 17) |

---

### 0.10 Notifications & Communication

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 64 | Email provider | Mailgun / Postmark / Resend / SES | **Resend** (modern API) or **Postmark** (deliverability) |
| 65 | Transactional emails | Hardcoded Blade / DB-managed templates | **DB-managed templates** — editable by team without deploy |
| 66 | SMS | No / Twilio / Vonage | Add via Laravel Notification channel — swap provider easily |
| 67 | Push notifications | No / Firebase FCM | Add channel now even if not used at launch |
| 68 | Notification log | No / Yes (`notifications` table) | **Yes** — Laravel's built-in DB channel, query later |
| 69 | Marketing email | Transactional only / + Campaigns | Separate tool (Klaviyo, Mailchimp) — never mix with transactional |

---

### 0.11 Search

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 70 | Search engine | DB LIKE / Meilisearch / Typesense / Elasticsearch | **Meilisearch** (self-host) or **Typesense** (simpler ops) |
| 71 | Laravel Scout | Yes / No | **Yes** — clean abstraction, swap engines without rewriting queries |
| 72 | Faceted filtering | No / Yes (filter by price, size, color) | **Yes** — via Meilisearch filters, not SQL |
| 73 | Autocomplete / typeahead | No / Yes | **Yes** — hits search engine directly from frontend |
| 74 | Search analytics | No / Yes (what are users searching for?) | **Yes** — log queries + zero-result queries → product gap insights |

---

### 0.12 Infrastructure & Deployment

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 75 | Hosting model | Shared / VPS / Managed cloud / PaaS | **Laravel Forge + VPS** (start) → migrate to AWS/GCP at scale |
| 76 | Containerization | None / Docker Compose / Kubernetes | **Docker Compose** for dev; **K8s or ECS** for production at scale |
| 77 | PHP runtime | PHP-FPM / Swoole Octane / FrankenPHP | **FrankenPHP + Octane** — persistent worker, fastest cold-start |
| 78 | CI/CD | Manual deploy / GitHub Actions / GitLab CI | **GitHub Actions** — free, integrates with everything |
| 79 | Secrets management | `.env` file / AWS Secrets Manager / Vault | `.env` + Forge for now; **AWS Secrets Manager** in production |
| 80 | CDN | None / Cloudflare / CloudFront | **Cloudflare** — free tier covers most needs + WAF + DDoS |
| 81 | Object storage | Local disk / S3 / Cloudflare R2 | **Cloudflare R2** — S3-compatible, zero egress fees |
| 82 | Email delivery | Self-hosted / Resend / Postmark | **Resend** or **Postmark** — never self-host SMTP |
| 83 | Multi-region | No (start) / Yes | Start single-region; design stateless app layer so adding regions is painless |
| 84 | Queue driver | Database / Redis / SQS | **Redis** (local) → **SQS** (production managed) |

---

### 0.13 Observability & Quality

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 85 | Error tracking | None / Sentry / Flare | **Sentry** (open source) or **Flare** (Laravel-native) |
| 86 | APM / Tracing | None / Telescope / Datadog / New Relic | **Telescope** (dev/staging) + **Datadog** or **Laravel Pulse** (prod) |
| 87 | Log aggregation | Laravel log files / Papertrail / Grafana Loki | **Loki + Grafana** (self-host) or **Papertrail** |
| 88 | Uptime monitoring | None / Uptime Robot / BetterUptime | **BetterUptime** — status page included |
| 89 | Test coverage | None / Feature tests / Full TDD | **Pest + Feature tests** for all happy paths + critical edge cases |
| 90 | Code style | None / Pint | **Laravel Pint** + run in CI — zero discussion about formatting |

---

### 0.14 Security & Compliance

| # | Decision | Options | Recommended |
|---|----------|---------|-------------|
| 91 | WAF | None / Cloudflare WAF | **Cloudflare WAF** — free plan covers basics |
| 92 | Rate limiting | None / Laravel throttle / Cloudflare | **Both** — Cloudflare at edge, Laravel for API routes |
| 93 | DDoS protection | None / Cloudflare | **Cloudflare** — included free |
| 94 | PCI compliance | Not needed / SAQ-A / Full PCI | **SAQ-A** with Stripe Elements — Stripe handles card data, you never touch it |
| 95 | GDPR | Not needed / Required | Required if any EU customers: cookie consent, data export, deletion |
| 96 | Audit log | No / Append-only `audit_log` table | **Yes** — who changed what, when; needed for disputes and compliance |
| 97 | 2FA | No / Yes for admin | **Yes for admin** — Filament has built-in 2FA |
| 98 | API authentication | Session / Sanctum tokens / OAuth | **Sanctum** for SPA + mobile; **OAuth (Passport)** if third-party API consumers |
| 99 | Dependency audit | None / `composer audit` in CI | **Yes** — one line in CI pipeline, catches known CVEs |

---

### 0.15 Scalability Design Rules (non-negotiable)

These are not decisions — they are **rules to follow from day one** regardless of current scale:

```
✅  Stateless application layer
    → No local file sessions, no local cache — always Redis/DB
    → Any server can handle any request (required for horizontal scaling)

✅  All slow work goes to a queue
    → Email, image resizing, search index sync, webhooks, PDF generation
    → HTTP response must return in <200ms for user-facing endpoints

✅  Idempotent jobs
    → Every queue job must be safe to run twice (payment webhook, stock sync)
    → Use unique job IDs or DB upserts to handle duplicates

✅  Feature flags from day one
    → Use `spatie/laravel-feature-flags` or simple DB table
    → Deploy code off, enable per user/group/% rollout without deploy

✅  Event-driven internal communication
    → Use Laravel Events for cross-module side effects (Order placed → Email + Stock deduct + Analytics)
    → Never call Module B directly from Module A — dispatch an event

✅  Snapshot immutability for orders
    → Copy price, product name, tax rate into order_items at checkout time
    → Never join order to live product table for display — product may change

✅  Backward-compatible migrations only
    → Add columns nullable first, never rename/drop without 2-deploy cycle
    → Zero-downtime deploy requires old and new code to run simultaneously

✅  Secrets never in code
    → All credentials in `.env` / Secrets Manager
    → Rotate credentials without code change

✅  UTC everywhere, format in UI
    → Store all times in UTC, convert to user's timezone at render time only
```

---

### 0.16 Recommended Package Baseline

| Purpose | Package |
|---------|---------|
| Admin panel | `filament/filament` |
| Media / images | `spatie/laravel-medialibrary` |
| Translations (DB) | `spatie/laravel-translatable` |
| Permission & roles | `spatie/laravel-permission` |
| Activity log / audit | `spatie/laravel-activitylog` |
| Soft deletes + trash | Built-in Laravel |
| Nested categories | `lazychaser/laravel-nestedset` |
| Sitemap | `spatie/laravel-sitemap` |
| Search | `laravel/scout` + Meilisearch driver |
| Payment (Stripe) | `stripe/stripe-php` + custom gateway abstraction |
| Geo / IP | `stevebauman/location` |
| Feature flags | `laravel/pennant` (official, Laravel 10+) |
| Money formatting | `moneyphp/money` |
| PDF (invoices) | `barryvdh/laravel-dompdf` or `spatie/browsershot` |
| Queue dashboard | `laravel/horizon` |
| Health checks | `spatie/laravel-health` |
| API resources | Built-in Laravel (`JsonResource`) |
| Testing | `pestphp/pest` + `pestphp/pest-plugin-laravel` |
| Code style | `laravel/pint` |
| Security audit | `enlightn/enlightn` |

---

## 1. High-Level Architecture Overview

### 1.1 Full Traffic Flow

```
                        ┌─────────────────────────────────────────┐
                        │         INTERNET (Users / Bots)         │
                        └───────────────────┬─────────────────────┘
                                            │
                        ┌───────────────────▼─────────────────────┐
                        │           CLOUDFLARE EDGE               │
                        │  WAF · DDoS · Rate Limit · llms.txt     │
                        │  Image CDN · Static assets · Cache      │
                        │  CF-IPCountry header (geo detection)    │
                        └───────────────────┬─────────────────────┘
                                            │ (cache miss only)
                        ┌───────────────────▼─────────────────────┐
                        │            LOAD BALANCER                │
                        │       Nginx / AWS ALB / HAProxy         │
                        │   SSL termination · health checks       │
                        └──────────┬────────────────┬────────────┘
                                   │                │
               ┌───────────────────▼──┐     ┌───────▼──────────────────┐
               │    APP SERVER 1      │     │     APP SERVER N          │
               │  Laravel 13 + PHP 8.5│     │  (auto-scale)             │
               │  FrankenPHP + Octane │ ... │  FrankenPHP + Octane      │
               │  Inertia.js SSR      │     │  Inertia.js SSR           │
               └──────────┬───────────┘     └───────────┬──────────────┘
                          │                             │
                          └──────────────┬──────────────┘
                                         │
              ┌──────────────────────────┼──────────────────────────────┐
              │                          │                              │
  ┌───────────▼──────────┐  ┌────────────▼──────────┐  ┌───────────────▼──────┐
  │     REDIS CLUSTER    │  │   DATABASE CLUSTER    │  │    QUEUE WORKERS     │
  │                      │  │                       │  │                      │
  │ · Sessions           │  │  [Primary RW]         │  │  Laravel Horizon     │
  │ · App cache          │  │       │ replication   │  │  Supervisord         │
  │ · Queues (Horizon)   │  │  [Replica 1 - reads]  │  │                      │
  │ · Rate limit ctrs    │  │  [Replica 2 - reports]│  │  Queues:             │
  │ · Inventory locks    │  │       │               │  │  · critical          │
  │ · Feature flags      │  │  PgBouncer (pooling)  │  │  · default           │
  └──────────────────────┘  └───────────────────────┘  │  · search-sync       │
                                                        │  · reporting         │
  ┌───────────────────────┐  ┌───────────────────────┐  │  · cleanup           │
  │    SEARCH ENGINE      │  │    OBJECT STORAGE     │  └──────────────────────┘
  │                       │  │                       │
  │  Meilisearch /        │  │  Cloudflare R2 / S3   │
  │  Typesense            │  │  · Product images     │
  │  via Laravel Scout    │  │  · User uploads       │
  └───────────────────────┘  │  · Invoices (private) │
                             └───────────────────────┘
  ┌───────────────────────┐  ┌───────────────────────┐
  │   EXTERNAL SERVICES   │  │  OBSERVABILITY STACK  │
  │                       │  │                       │
  │  · Stripe (payments)  │  │  · Laravel Pulse      │
  │  · Resend (email)     │  │  · Sentry             │
  │  · EasyPost (ship)    │  │  · Grafana + Loki     │
  │  · TaxJar / Stripe Tax│  │  · Uptime monitoring  │
  │  · Socialite (OAuth)  │  └───────────────────────┘
  └───────────────────────┘
```

---

### 1.2 Request Lifecycle (per HTTP request)

```
Browser
  │
  ├─[1]─► Cloudflare edge
  │         → Serve static asset from CDN cache? → return immediately
  │         → WAF block? → 403
  │         → Rate limit hit? → 429
  │         → Attach CF-IPCountry header
  │
  ├─[2]─► Load balancer → pick healthy app server
  │
  ├─[3]─► FrankenPHP / Octane (persistent PHP worker — no bootstrap cost)
  │
  ├─[4]─► Laravel middleware stack
  │         DetectLocale → SetLocale → Auth → Throttle → CSRF
  │
  ├─[5]─► Route → Controller → Service
  │         → Read: hit Redis L2 cache first → DB replica if miss
  │         → Write: always DB primary
  │
  ├─[6]─► Slow side effects → dispatch to queue (do NOT block response)
  │         e.g. email, search re-index, analytics event
  │
  └─[7]─► Response < 200ms target for user-facing pages
```

---

### 1.3 Internal Event Flow (module communication)

```
Order Placed
    │
    ▼
Laravel Event: OrderPlaced
    │
    ├──► Listener: SendOrderConfirmationEmail     → queue: default
    ├──► Listener: DeductInventory                → queue: critical
    ├──► Listener: SyncToSearchIndex              → queue: search-sync
    ├──► Listener: RecordAnalyticsEvent           → queue: reporting
    ├──► Listener: TriggerFulfillmentWebhook      → queue: default
    └──► Listener: IssueLoyaltyPoints             → queue: default

Modules NEVER call each other directly.
All cross-module side effects go through events.
```

---

### 1.4 Data Flow — Reads vs Writes

```
Read-heavy paths (product listing, search, homepage):
  Browser → CDN cache → [miss] → App → Redis L2 → [miss] → DB Replica 1

Write paths (order, payment, stock update):
  Browser → App → DB Primary → invalidate Redis cache → dispatch queue jobs

Analytics / reports (never touch production DB):
  Replica 2 (dedicated) → or → ClickHouse / data warehouse
```

---

### 1.5 Environments

```
local       → Docker Compose (app + postgres + redis + meilisearch + mailpit)
staging     → Mirror of production, single server, seeded data, Telescope ON
production  → Multi-server, Pulse ON, Telescope OFF, Sentry ON
```

---

## 2. Application Layer

| Component | Choice | Notes |
|-----------|--------|-------|
| Framework | Laravel 13 | API + Web, Blade or Inertia.js |
| Runtime | PHP 8.5 + FrankenPHP | Use Octane for persistent worker mode |
| Web Server | Caddy (via FrankenPHP) or Nginx | FrankenPHP recommended for perf |
| Process Manager | Supervisord | Manage Octane + Horizon workers |

### Laravel Key Modules

**Architecture pattern:** Modular Monolith
- Each module is self-contained: owns its models, routes, events, jobs, and service classes
- Modules communicate **only via Laravel Events** — never direct cross-module calls
- Easy to extract a module into a microservice later if needed
- All modules live under `app/Modules/`, each auto-loaded via its own `ServiceProvider`

---

#### Module Map

```
app/
├── Modules/
│   ├── Catalog/             ← products, variants, categories, brands, attributes
│   ├── Inventory/           ← stock levels, warehouses, reservations, locking
│   ├── Cart/                ← cart lifecycle, guest merge, price snapshot
│   ├── Orders/              ← order state machine, events log, RMA/returns
│   ├── Payments/            ← gateway abstraction, Stripe, webhook handling
│   ├── Shipping/            ← zones, rates, EasyPost, tracking events
│   ├── Customers/           ← auth, profiles, addresses, groups, B2B
│   ├── Promotions/          ← coupons, automatic discounts, flash sales
│   ├── Loyalty/             ← points ledger, rewards, redemption
│   ├── Affiliates/          ← referral codes, commission ledger
│   ├── Notifications/       ← email, SMS, push, in-app, DB log
│   ├── Content/             ← pages, blog, FAQ, policy, translations
│   ├── Reviews/             ← product reviews, moderation, ratings
│   ├── Search/              ← Scout integration, indexing, analytics
│   ├── Seo/                 ← meta, JSON-LD, sitemap, llms.txt, hreflang
│   ├── Geo/                 ← locale detection, geo-redirect, currency
│   ├── Analytics/           ← event tracking, sales reports, dashboards
│   └── Admin/               ← Filament panel, resources, widgets
│
├── Infrastructure/
│   ├── Cache/               ← cache key conventions, tag management
│   ├── Storage/             ← Cloudflare R2 / S3 disk abstraction
│   ├── Payment/             ← PaymentGateway contract + Stripe driver
│   ├── Carrier/             ← ShippingCarrier contract + EasyPost driver
│   └── Locale/              ← currency formatting, number/date helpers
│
└── Support/
    ├── Money.php            ← value object: amount (int cents) + currency
    ├── Ulid.php             ← ULID generation helper
    └── Enums/               ← shared enums (OrderStatus, PaymentStatus …)
```

---

#### Standard Internal Structure (every module follows this)

```
Modules/Catalog/
│
├── Models/
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── ProductTranslation.php
│   ├── Category.php
│   ├── CategoryTranslation.php
│   ├── Brand.php
│   └── ProductImage.php
│
├── Actions/                 ← single-purpose, invokable classes (the "doing")
│   ├── CreateProduct.php
│   ├── UpdateProduct.php
│   ├── PublishProduct.php
│   └── DeleteProduct.php
│
├── Data/                    ← DTOs (typed input/output, no magic arrays)
│   ├── ProductData.php
│   └── VariantData.php
│
├── Events/                  ← what happened (past tense)
│   ├── ProductCreated.php
│   ├── ProductUpdated.php
│   └── ProductDeleted.php
│
├── Listeners/               ← reacts to events from OTHER modules
│   └── InvalidateCatalogCacheOnOrderPlaced.php
│
├── Jobs/                    ← queued background work
│   ├── SyncProductToSearch.php
│   └── ProcessProductImages.php
│
├── Http/
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   └── CategoryController.php
│   ├── Requests/            ← Form requests (validation)
│   │   ├── StoreProductRequest.php
│   │   └── UpdateProductRequest.php
│   └── Resources/           ← API JSON resources
│       ├── ProductResource.php
│       └── ProductCollection.php
│
├── Observers/
│   └── ProductObserver.php  ← hooks into Eloquent lifecycle
│
├── Policies/
│   └── ProductPolicy.php    ← authorization rules
│
├── routes/
│   ├── web.php
│   └── api.php
│
└── CatalogServiceProvider.php  ← registers routes, observers, event listeners
```

> Every other module follows the same pattern. Not every folder is required — only create what the module actually needs.

---

#### Module Responsibility Summary

| Module | Owns | Key Events Emitted |
|--------|------|--------------------|
| **Catalog** | Products, variants, categories, brands, images, attributes | `ProductCreated`, `ProductUpdated`, `ProductDeleted` |
| **Inventory** | Stock levels, warehouses, reservations | `StockReserved`, `StockReleased`, `StockDepleted` |
| **Cart** | Cart lifecycle, item pricing snapshot, guest→user merge | `CartItemAdded`, `CartAbandoned` |
| **Orders** | Order state machine, `order_events` log, RMA | `OrderPlaced`, `OrderConfirmed`, `OrderShipped`, `OrderCancelled`, `RefundIssued` |
| **Payments** | Gateway abstraction, Stripe webhooks, `payment_events` | `PaymentAuthorized`, `PaymentFailed`, `PaymentRefunded` |
| **Shipping** | Zones, rate calculation, EasyPost labels, tracking | `ShipmentCreated`, `ShipmentDelivered` |
| **Customers** | Auth, profiles, address book, groups, B2B accounts | `CustomerRegistered`, `CustomerDeleted` |
| **Promotions** | Coupons, auto discounts, flash sale prices | `CouponApplied`, `DiscountApplied` |
| **Loyalty** | Points ledger, earn/spend rules | `PointsEarned`, `PointsRedeemed` |
| **Affiliates** | Referral codes, commission ledger | `CommissionEarned` |
| **Notifications** | Renders + dispatches all outbound messages | _(consumes events, emits none)_ |
| **Content** | Pages, blog, FAQ, policy pages, translations | `PagePublished` |
| **Reviews** | Product reviews, ratings, moderation | `ReviewApproved` |
| **Search** | Scout index sync, search analytics | _(consumes events, emits none)_ |
| **Seo** | Meta tags, JSON-LD, sitemap, llms.txt, hreflang | _(generates files, emits none)_ |
| **Geo** | Locale/currency detection, IP geolocation | _(middleware only)_ |
| **Analytics** | Event log, sales reports, conversion funnels | _(consumes events, emits none)_ |
| **Admin** | Filament resources, custom pages, widgets | _(thin layer over other modules)_ |

---

#### Cross-Module Event Wiring (examples)

```
OrderPlaced ──────────────────────────────────────────────────────────┐
  │                                                                    │
  ├── Inventory::DeductReservedStock          (queue: critical)        │
  ├── Payments::ConfirmPaymentCapture          (queue: critical)       │
  ├── Notifications::SendOrderConfirmEmail     (queue: default)        │
  ├── Shipping::CreateFulfillmentRequest       (queue: default)        │
  ├── Loyalty::AwardPointsForOrder             (queue: default)        │
  ├── Affiliates::RecordCommission             (queue: default)        │
  ├── Analytics::RecordSaleEvent               (queue: reporting)      │
  └── Search::UpdateProductSalesRankScore      (queue: search-sync)    │
                                                                       │
PaymentFailed ─────────────────────────────────────────────────────────┘
  ├── Inventory::ReleaseReservation            (queue: critical)
  ├── Orders::TransitionToPendingPayment       (queue: critical)
  └── Notifications::SendPaymentFailedEmail    (queue: default)

CustomerDeleted
  ├── Orders::AnonymizePiiInOrders             (queue: default)
  ├── Reviews::AnonymizeReviews                (queue: default)
  └── Loyalty::ForfeitsPoints                  (queue: default)
```

---

## 3. Database Layer

### Engine Choice

| Engine | Recommendation | Reason |
|--------|---------------|--------|
| **PostgreSQL 16** | Preferred | Better JSON support, partial indexes, LISTEN/NOTIFY, row-level locks, native UUID |
| MySQL 8.0+ | Acceptable | Wider hosting support, familiar to most teams |

**Pick one and commit.** Do not mix in production.

---

### Topology

```
                  ┌─────────────────┐
  Writes ────────►│   Primary (RW)  │
                  └────────┬────────┘
                           │ replication (async)
              ┌────────────┴────────────┐
              ▼                         ▼
     ┌────────────────┐      ┌────────────────┐
     │  Read Replica 1│      │  Read Replica 2│
     │  (listings,    │      │  (reports,     │
     │   product API) │      │   analytics)   │
     └────────────────┘      └────────────────┘
```

- Laravel config: `DB_READ` / `DB_WRITE` connections (sticky for same request)
- Replica 2 dedicated to heavy reporting queries — never touches user-facing reads

---

### Connection Pooling

**Critical at scale.** PHP spawns a new DB connection per request without pooling.

| Tool | Use With |
|------|----------|
| **PgBouncer** (transaction mode) | PostgreSQL |
| **ProxySQL** | MySQL |

> Without pooling, 500 concurrent users = 500 open DB connections = DB crash.

---

### Core Schema Design Rules

#### Money — Always Integer (cents)
```
price BIGINT NOT NULL          ← store 2999 = $29.99, NEVER float/decimal
currency CHAR(3) NOT NULL      ← 'USD', 'EUR'
```
Never use `FLOAT` or `DECIMAL` for money. Floating point rounding errors cause real financial bugs.

#### IDs — Use UUIDs (not auto-increment)
```sql
id UUID PRIMARY KEY DEFAULT gen_random_uuid()
```
- Safe to expose in URLs (no enumeration attacks)
- Safe to generate in application before insert
- Safe for future sharding / merging datasets

#### Timestamps — Always UTC
```
created_at TIMESTAMPTZ NOT NULL DEFAULT now()
updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
deleted_at TIMESTAMPTZ NULL      ← soft delete
```

#### Soft Deletes — On all major tables
- Products, Categories, Orders, Customers, Coupons
- Never hard-delete business data; use `deleted_at`
- Add partial index: `WHERE deleted_at IS NULL` on all queries

---

### Core Table Map

```
── users                      id, email, password, locale, currency
── user_addresses             id, user_id, type, country, city, ...
── products                   id, slug, status, brand_id, ...
── product_translations       id, product_id, locale, name, description
── product_variants           id, product_id, sku, price, stock_reserved, stock_available
── product_attributes         id, product_id, key, value (JSON or EAV)
── product_images             id, product_id, variant_id, url, sort_order
── categories                 id, parent_id, slug, lft, rgt (nested set)
── category_translations      id, category_id, locale, name
── category_product           pivot: category_id, product_id
── brands                     id, slug, name, logo_url
── inventory                  id, variant_id, warehouse_id, qty_on_hand, qty_reserved
── warehouses                 id, name, country, priority
── carts                      id, user_id (nullable), session_id, expires_at
── cart_items                 id, cart_id, variant_id, qty, unit_price_snapshot
── orders                     id, user_id, status, total, currency, ...
── order_items                id, order_id, variant_id, qty, unit_price, total
── order_events               id, order_id, event, payload (JSON) ← append-only log
── payments                   id, order_id, gateway, status, amount, gateway_ref
── payment_events             id, payment_id, event, payload (JSON) ← append-only log
── coupons                    id, code, type, value, usage_limit, used_count
── coupon_usage               id, coupon_id, user_id, order_id, used_at
── reviews                    id, product_id, user_id, rating, body, status
── wishlists                  id, user_id, name
── wishlist_items             id, wishlist_id, variant_id
── pages                      id, slug, type (faq, policy, blog)
── page_translations          id, page_id, locale, title, body
── audit_log                  id, user_id, action, model_type, model_id, before, after
```

---

### Product Variants & Attributes Strategy

**Problem:** Products have dynamic attributes (size, color, material). Three common approaches:

| Approach | When |
|----------|------|
| **JSON column** on `product_variants` | Simple shops, attributes vary wildly per product |
| **EAV** (attribute/value rows) | Need to filter/search by attribute in SQL |
| **Dedicated columns per type** | Fixed attribute sets (e.g. clothing only) |

**Recommendation:** JSON column for storage + Meilisearch for attribute filtering. Avoids EAV complexity, still searchable.

---

### Inventory Locking (Critical for Flash Sales)

```
Naive approach (WRONG):
  1. Read stock → 2. Check > 0 → 3. Decrement   ← race condition at high traffic

Correct approach:
  Option A — Optimistic locking:
    UPDATE inventory SET qty = qty - 1, version = version + 1
    WHERE id = ? AND qty >= 1 AND version = ?
    → retry if 0 rows affected

  Option B — Redis atomic decrement (fastest):
    DECRBY inventory:{variant_id} {qty}
    → if result < 0: INCRBY back + reject
    → sync Redis → DB via queue job

  Option C — DB row lock (simplest):
    SELECT FOR UPDATE on inventory row inside transaction
```

**Recommendation:** Redis for hot items (flash sales), DB row lock for normal checkout.

---

### Order State Machine

Orders must never skip states. Use an append-only `order_events` table as the source of truth.

```
pending_payment
    │
    ▼
payment_authorized
    │
    ▼
confirmed ──────────────────────► cancelled
    │                                  ▲
    ▼                                  │
processing ────────────────────────────┤
    │                                  │
    ▼                                  │
shipped ────────────────────────────────┤
    │
    ▼
delivered ──────────────────────► refunded
    │
    ▼
completed
```

- `orders.status` = derived from last `order_events` entry
- `order_events` = immutable, append-only, full payload stored as JSON
- Enables full audit trail, dispute resolution, and event replay

---

### Migrations Strategy

| Rule | Why |
|------|-----|
| Never rename a column directly — add new, backfill, drop old later | Zero-downtime deploy safe |
| Never add NOT NULL without a default | Fails on large tables during migration |
| Use `after()` / `before()` for column ordering | Avoids full table rebuild on MySQL |
| Index new foreign keys immediately | Prevents lock-ups on large tables |
| Squash migrations per major release | Keeps `migrate:fresh` fast in dev |
| Separate schema migrations from data migrations | Run independently, easier to rollback |

**Zero-downtime deploy pattern:**
```
Deploy 1: add new column (nullable)
Deploy 2: backfill data via queue job
Deploy 3: add NOT NULL constraint + drop old column
```

---

### Read vs Write Separation in Laravel

```php
// config/database.php
'mysql' => [
    'read'  => ['host' => [env('DB_READ_HOST')]],
    'write' => ['host' => [env('DB_WRITE_HOST')]],
    'sticky' => true,   // same request reads its own writes
]
```

Use `->onWriteConnection()` for queries that must hit primary (e.g. after a write).

---

### Analytics / Reporting — Separate OLAP

**Never run heavy reports on production DB replicas.** At scale:

| Tool | Purpose |
|------|---------|
| **ClickHouse** | Event analytics, sales reporting (columnar, extremely fast) |
| **BigQuery / Redshift** | Cloud data warehouse if going full cloud |
| **Simple:** replica 2 | Acceptable at small scale, but isolate the replica |

Feed via: events pushed to queue → async writer to ClickHouse/warehouse

---

### Cache Database (Redis)
- **Version:** Redis 7+ (Cluster mode for scale)
- **Responsibilities:**
  - Session storage
  - Application cache (query cache, full-page snippets)
  - Queue driver (Laravel Horizon)
  - Rate limiting counters
  - Flash sale inventory locks (atomic operations)

---

## 4. Caching Strategy

```
Request → L1: Route/Response Cache (Nginx)
        → L2: Full-Page Cache (Redis / Varnish)
        → L3: Query Cache (Laravel Cache facade → Redis)
        → L4: Database
```

| Cache Type | TTL | Invalidation Trigger |
|------------|-----|----------------------|
| Product listings | 5–15 min | Product update event |
| Product detail | 30 min | Product update event |
| Homepage / banners | 10 min | Content update |
| Cart | Session lifetime | Item add/remove |
| User session | 2 hours (sliding) | Logout / expiry |

---

## 5. Queue & Background Jobs

**Driver:** Redis via **Laravel Horizon**

| Queue | Priority | Jobs |
|-------|----------|------|
| `critical` | Highest | Payment processing, order confirmation |
| `default` | Normal | Email notifications, webhooks |
| `search-sync` | Normal | Re-index product on update |
| `reporting` | Low | Analytics aggregation |
| `cleanup` | Lowest | Purge old data, temp files |

---

## 6. Search

- **Engine:** Meilisearch (self-hosted) or Typesense
- **Laravel integration:** Laravel Scout
- **Indexed:** Products (name, description, tags, category, attributes)
- **Sync:** Via queue job on product create/update/delete

---

## 7. File / Media Storage

| Asset Type | Storage | Delivery |
|------------|---------|----------|
| Product images | S3 / Cloudflare R2 | CDN URL |
| User uploads | S3 / Cloudflare R2 | CDN URL |
| Invoices (PDF) | S3 private bucket | Signed URL |
| App assets (CSS/JS) | CDN + versioned | Cache-busted on deploy |

**Image processing:** Laravel with Spatie Media Library + on-the-fly transforms

---

## 8. CDN & Edge

- **Provider:** Cloudflare (recommended) or AWS CloudFront
- **Cached at edge:** Static assets, product images, public pages
- **Bypassed:** Cart, checkout, authenticated pages
- **DDoS protection:** Cloudflare WAF + Rate Limiting rules

---

## 9. Containerization & Deployment

```
docker-compose (dev)
│
├── app        (PHP 8.5 + FrankenPHP/Octane)
├── nginx      (optional reverse proxy)
├── mysql      (or postgres)
├── redis
├── meilisearch
└── mailpit    (local mail dev)

Kubernetes (production)
│
├── Deployment: app (auto-scale 2–10 pods)
├── Deployment: horizon-worker (queue)
├── StatefulSet: redis
├── External: RDS / managed MySQL
└── External: S3 / managed storage
```

**CI/CD Pipeline:**
```
Git Push → GitHub Actions / GitLab CI
  → Run tests (Pest)
  → Build Docker image
  → Push to registry
  → Rolling deploy to K8s / ECS
  → Run migrations (zero-downtime)
  → Cache warm-up
```

---

## 10. Monitoring & Observability

| Tool | Purpose |
|------|---------|
| Laravel Telescope | Local/staging debug (queries, jobs, requests) |
| Laravel Pulse | Production real-time dashboard |
| Prometheus + Grafana | Metrics (RPS, queue depth, cache hit rate) |
| Sentry | Error tracking + performance tracing |
| Uptime Robot / BetterUptime | External uptime monitoring |
| Cloudflare Analytics | Edge traffic insights |

---

## 11. Security Layers

- WAF at CDN edge (Cloudflare)
- Rate limiting: API routes (Laravel `throttle` middleware)
- HTTPS enforced everywhere
- Laravel Sanctum (SPA/API auth) or Jetstream
- CSRF protection on all forms
- SQL injection: Eloquent ORM (parameterized queries only)
- Secrets: `.env` + AWS Secrets Manager / HashiCorp Vault (prod)
- Dependency audits: `composer audit` in CI pipeline

---

## 12. Scaling Playbook

| Traffic Level | Action |
|---------------|--------|
| ~1K req/min | Single server + Redis + read replica |
| ~10K req/min | 2–3 app servers + Horizon workers scaled out |
| ~100K req/min | K8s auto-scaling + ElastiCache + Aurora RDS |
| ~1M+ req/min | Multi-region, edge caching, read-heavy DB sharding |

---

## 15. SEO Architecture

### On-Page SEO Infrastructure

| Layer | Mechanism | Scope |
|-------|-----------|-------|
| Meta tags | Dynamic per route (title, description, OG, Twitter Card) | All public pages |
| Canonical URLs | Auto-generated, locale-aware | All public pages |
| Hreflang | Per-locale alternate links in `<head>` | Geo/multilingual pages |
| Robots meta | `noindex` on cart, checkout, search with no results | Selective |
| Pagination | `rel="next"` / `rel="prev"` + canonical on paginated lists | Category, blog |
| Breadcrumbs | Structured data + visible UI component | Product, category |

### URL Structure Strategy

```
yourdomain.com/                          ← Homepage
yourdomain.com/[category]/               ← Category listing
yourdomain.com/[category]/[subcategory]/ ← Sub-category
yourdomain.com/products/[slug]/          ← Product detail
yourdomain.com/blog/[slug]/              ← Blog/content
yourdomain.com/[geo]/                    ← Geo-targeted (optional subfolder)
```

**Rules:**
- Lowercase slugs, hyphens only, no trailing slash inconsistency
- Permanent 301 redirects on slug changes
- Slugs stored in DB, cached, never auto-generated from titles on update

---

## 16. Geo Targeting

### Strategy Options (choose one)

| Approach | How | Best For |
|----------|-----|----------|
| **Subdomain** | `fr.shop.com`, `de.shop.com` | Strong geo separation, easy CDN rules |
| **Subfolder** | `shop.com/fr/`, `shop.com/de/` | Consolidates domain authority (recommended) |
| **ccTLD** | `shop.fr`, `shop.de` | Maximum local trust, harder to maintain |

**Recommended: Subfolder** — keeps SEO authority on one domain, works well with Laravel localization.

### Geo Implementation in Laravel

```
Middleware Stack:
  DetectLocale         → reads Accept-Language, IP geolocation, user preference
  SetLocaleForSession  → persists to session + cookie
  InjectHreflang       → adds alternate links to response

Route Groups:
  Route::prefix('{locale}')->group(...)   ← locale-prefixed routes
  Route::middleware('geo.redirect')       ← auto-redirect by IP (optional)
```

### Geo Data per Locale
- Currency (price formatting + conversion)
- Tax rules per region
- Shipping zones
- Legal pages (GDPR for EU, etc.)
- Date/number formatting via `Illuminate\Support\Facades\App::setLocale()`

### IP Geolocation
- **Library:** `stevebauman/location` (free, MaxMind GeoIP2 backend)
- **Cloudflare:** Use `CF-IPCountry` header (free, no extra service needed)
- **Fallback:** User-selectable language/region switcher in UI

---

## 17. JSON-LD Structured Data

Every public page type gets appropriate Schema.org markup injected in `<head>` via a dedicated `JsonLd` service class.

### Schema Map by Page Type

| Page | Schema Types |
|------|-------------|
| Homepage | `WebSite` + `Organization` + `SiteNavigationElement` |
| Category / Listing | `CollectionPage` + `BreadcrumbList` + `ItemList` |
| Product Detail | `Product` + `Offer` + `AggregateRating` + `BreadcrumbList` |
| Product with Variants | `Product` → `hasVariant` → array of `ProductModel` |
| Blog Post | `Article` + `BreadcrumbList` + `Author` (Person/Organization) |
| FAQ Page | `FAQPage` + `Question` + `Answer` |
| Search Results | `SearchResultsPage` |
| Cart / Checkout | none (noindex anyway) |

### Key Product JSON-LD Fields

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "...",
  "sku": "...",
  "gtin13": "...",          ← barcode/EAN if available
  "brand": { "@type": "Brand", "name": "..." },
  "image": ["url1", "url2"],
  "description": "...",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.7",
    "reviewCount": "128"
  },
  "offers": {
    "@type": "Offer",
    "price": "29.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "priceValidUntil": "2027-01-01",
    "url": "...",
    "seller": { "@type": "Organization", "name": "..." }
  }
}
```

### Organization / Store JSON-LD (sitewide)

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Shop Name",
  "url": "https://...",
  "logo": "...",
  "contactPoint": { "@type": "ContactPoint", "contactType": "customer service", ... },
  "sameAs": ["https://facebook.com/...", "https://instagram.com/..."]
}
```

### Implementation

```
app/
└── Services/
    └── Seo/
        ├── JsonLdBuilder.php        ← fluent builder
        ├── Schemas/
        │   ├── ProductSchema.php
        │   ├── CategorySchema.php
        │   ├── ArticleSchema.php
        │   ├── OrganizationSchema.php
        │   └── BreadcrumbSchema.php
        └── JsonLdMiddleware.php     ← injects into Blade layout
```

---

## 18. Sitemaps

### Sitemap Architecture

```
/sitemap.xml                    ← Sitemap Index (points to all below)
  ├── /sitemap-pages.xml        ← Static pages (about, contact, etc.)
  ├── /sitemap-categories.xml   ← All category pages
  ├── /sitemap-products.xml     ← Products (paginated: -1.xml, -2.xml ...)
  ├── /sitemap-blog.xml         ← Blog / content pages
  └── /sitemap-[locale].xml     ← Per-locale sitemaps (if multilingual)
```

### Sitemap Fields per URL

```xml
<url>
  <loc>https://shop.com/products/product-slug/</loc>
  <lastmod>2026-04-06</lastmod>
  <changefreq>weekly</changefreq>
  <priority>0.8</priority>
  <xhtml:link rel="alternate" hreflang="en" href="..."/>
  <xhtml:link rel="alternate" hreflang="fr" href="..."/>
  <image:image>
    <image:loc>https://cdn.shop.com/products/img.jpg</image:loc>
    <image:title>Product Name</image:title>
  </image:image>
</url>
```

### Generation Strategy

| Approach | When to Use |
|----------|------------|
| On-demand (cached) | Small-medium catalog (<50K URLs) |
| Pre-generated via queue job | Large catalog (>50K URLs) |
| Streaming generation | Massive catalog (>500K URLs) |

**Package:** `spatie/laravel-sitemap` — supports dynamic generation, image sitemaps, and custom XML extensions.

**Auto-regenerate:** Queue job fires on product/category create/update/delete → regenerates affected sitemap chunk → pings Google + Bing.

---

## 19. LLMs.txt — AI Crawler Friendly

`llms.txt` is the emerging standard (llmstxt.org) that tells AI crawlers (GPT, Gemini, Claude, Perplexity) what your site is about and which content to index or ignore.

### Files to Publish

| File | URL | Purpose |
|------|-----|---------|
| `llms.txt` | `/llms.txt` | Site overview + key links for AI |
| `llms-full.txt` | `/llms-full.txt` | Full content dump for deep indexing |

### llms.txt Structure

```markdown
# Shop Name

> One-line description of the shop and what it sells.

This site sells [products]. It ships to [regions]. Prices in [currencies].

## Products
- [Catalog](https://shop.com/products/): Full product listing
- [Categories](https://shop.com/categories/): Browse by category
- [New Arrivals](https://shop.com/new/): Latest products

## Information
- [About Us](https://shop.com/about/)
- [Shipping Policy](https://shop.com/shipping/)
- [Return Policy](https://shop.com/returns/)
- [FAQ](https://shop.com/faq/)

## Contact
- [Contact Page](https://shop.com/contact/)
- Email: support@shop.com

## Optional: robots hint for AI
Sitemap: https://shop.com/sitemap.xml
```

### llms-full.txt Strategy

- Generated via queue job, cached as static file on S3/CDN
- Contains: all products (name, description, price, category, availability), all FAQ content, all policy pages
- Regenerated nightly or on significant catalog update
- Served with `Content-Type: text/plain` and long cache headers

### AI-Friendly Meta Tags (supplement llms.txt)

```html
<!-- In <head> for AI crawlers that read HTML -->
<meta name="description" content="...">           ← clear, factual
<meta name="robots" content="index, follow">
<link rel="alternate" type="text/plain"
      href="/llms.txt" title="LLMs.txt">
```

---

## 20. robots.txt

```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /cart
Disallow: /checkout/
Disallow: /account/
Disallow: /api/
Disallow: /?*             ← block query string URLs (faceted nav)
Allow: /?page=            ← allow pagination params

# AI Crawlers — allow full indexing
User-agent: GPTBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Googlebot
Allow: /

Sitemap: https://shop.com/sitemap.xml
LLMs: https://shop.com/llms.txt
```

---

## 21. SEO + Geo Module Structure

```
app/
└── Modules/
    └── Seo/
        ├── Contracts/
        │   └── SeoPageInterface.php
        ├── Services/
        │   ├── MetaTagService.php       ← title, desc, OG, Twitter
        │   ├── JsonLdBuilder.php
        │   ├── HreflangService.php
        │   ├── SitemapService.php
        │   └── LlmsTextService.php
        ├── Schemas/
        │   ├── ProductSchema.php
        │   ├── CategorySchema.php
        │   ├── ArticleSchema.php
        │   ├── OrganizationSchema.php
        │   └── BreadcrumbSchema.php
        ├── Jobs/
        │   ├── RegenerateSitemap.php
        │   └── RegenerateLlmsTxt.php
        ├── Middleware/
        │   ├── DetectLocale.php
        │   └── GeoRedirect.php
        └── Http/Controllers/
            ├── SitemapController.php
            ├── RobotsController.php
            └── LlmsController.php

resources/
└── views/
    └── seo/
        ├── meta.blade.php           ← included in layout <head>
        ├── jsonld.blade.php         ← script type=application/ld+json
        └── hreflang.blade.php       ← link rel=alternate tags
```

---

## 13. Tech Stack Summary

```
Language:       PHP 8.5
Framework:      Laravel 13
Runtime:        FrankenPHP + Octane
Web Server:     Caddy (via FrankenPHP)
Database:       MySQL 8 / PostgreSQL 16
Cache/Queue:    Redis 7
Search:         Meilisearch / Typesense
Storage:        AWS S3 / Cloudflare R2
CDN:            Cloudflare
Containers:     Docker + Kubernetes
CI/CD:          GitHub Actions
Monitoring:     Pulse + Telescope + Sentry
Testing:        Pest
SEO:            Spatie Laravel Sitemap + custom JsonLd service
Geo:            stevebauman/location + Cloudflare CF-IPCountry
LLMs:           llms.txt + llms-full.txt (queue-generated)
Structured Data: Schema.org JSON-LD (Product, Org, Article, FAQ, Breadcrumb)
```

---

## 14. Development Stages

```
Stage 1 — MVP
  └── Single server, SQLite→MySQL, Redis, basic queues

Stage 2 — Growth
  └── Read replica, Horizon, CDN for assets, S3 storage

Stage 3 — Scale
  └── Multi-server (load balanced), Redis Cluster, Meilisearch

Stage 4 — Enterprise
  └── Kubernetes, multi-region, managed DB, full observability
```

---

*Last updated: 2026-04-06 — added SEO, Geo, JSON-LD, Sitemap, LLMs.txt sections*
