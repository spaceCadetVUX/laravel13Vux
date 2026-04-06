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

## Dev ↔ Production Parity

- Local: Laragon + PostgreSQL 16 (manual binary) OR `postgres:16` in Docker Compose
- Production: Forge VPS + PostgreSQL 16 (auto) + PgBouncer
- Both must run **PostgreSQL 16** — never mix major versions
