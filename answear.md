# Pre-Flight Answers — My Shop Decisions

> Profile: **Single seller B2C · Single language · VPS / Laravel Forge · Small-to-medium scale**
> Last updated: 2026-04-06

---

## 0.1 Database Fundamentals

---

### #1 — Engine
**Decision: PostgreSQL 16**

You have no strong preference, so go with the better tool.
PostgreSQL wins on this stack because:
- Native ULID/UUID generation (`gen_random_uuid()`, no extension needed)
- Partial indexes → `WHERE deleted_at IS NULL` indexes are much cheaper
- Better row-level locking → critical for inventory reservation at checkout
- `JSONB` column type for product attributes is significantly faster than MySQL JSON
- `LISTEN/NOTIFY` useful for real-time stock events later
- Laravel supports it natively, Forge provisions it out of the box

**How to install on VPS via Forge:** select PostgreSQL when provisioning the server. Done.

---

### #2 — ID Strategy
**Decision: ULID**

Why not auto-increment INT?
- Exposes record counts (customer #4 knows you have 3 customers before them)
- Cannot be generated in application before insert (causes problems with event-sourced orders)
- Hard to shard later

Why ULID over UUID v4?
- ULID is **sortable by time** — `ORDER BY id` is chronological, no need for `created_at` index in many cases
- Shorter string (26 chars vs 36), URL-safe, no hyphens
- Generates in PHP: `Str::ulid()` — built into Laravel

```php
// In every migration:
$table->ulid('id')->primary();

// In every Model:
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Product extends Model {
    use HasUlids;
}
```

---

### #3 — Money Storage
**Decision: BIGINT (integer cents) — non-negotiable**

Never use `FLOAT` or `DECIMAL` for money. Ever.

```
$29.99  → store as  2999  (integer)
$0.50   → store as    50  (integer)
```

- Zero rounding errors
- Safe arithmetic in SQL (`SUM`, `AVG` work correctly)
- Use `moneyphp/money` value object in PHP for all arithmetic and formatting
- Convert to display string only at render time

```php
// Always work with Money objects, never raw integers in business logic
$price = Money::of(2999, 'USD');         // $29.99
$total = $price->multipliedBy(3);        // $89.97
echo $total->formatTo('en_US');          // "$89.97"
```

Store in DB:
```sql
price          BIGINT NOT NULL   -- amount in cents
currency       CHAR(3) NOT NULL  -- 'USD', 'EUR', 'GBP'
```

---

### #4 — Timestamps
**Decision: UTC — non-negotiable**

- All `created_at`, `updated_at`, `deleted_at` stored in UTC
- PostgreSQL column type: `TIMESTAMPTZ` (timezone-aware)
- Laravel default is already UTC if `APP_TIMEZONE=UTC` in `.env`
- Convert to user's local timezone **only at display time** in Blade/Inertia

```php
// .env
APP_TIMEZONE=UTC

// Display in user's timezone (Blade):
{{ $order->created_at->setTimezone(auth()->user()->timezone)->format('d M Y H:i') }}
```

Why it matters: flash sales, order timestamps, shipping ETAs — if stored in local time they break when servers move regions or DST changes.

---

### #5 — Soft Deletes
**Decision: `deleted_at` on all business tables**

Tables that MUST have soft deletes:
- `products`, `product_variants`
- `orders`, `order_items`
- `customers` (users)
- `categories`, `brands`
- `coupons`
- `reviews`

Tables that do NOT need soft deletes:
- `order_events` — append-only, never deleted
- `payment_events` — append-only, never deleted
- `audit_log` — append-only, never deleted
- `cart_items` — ephemeral, hard delete is fine

```php
// In migration:
$table->softDeletes(); // adds deleted_at TIMESTAMPTZ NULL

// In Model:
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model {
    use SoftDeletes;
}
```

Never hard-delete an order. Never hard-delete a customer (anonymize PII instead, keep the shell record for order history).

---

### #6 — Translations
**Decision: `spatie/laravel-translatable` with JSON columns**

You only need 1 language now — but use a translation-ready structure so adding a second language later requires **zero schema changes**.

How it works: translatable fields are stored as JSON in a single column.

```sql
-- Single column, all locales inside:
name  JSONB  →  {"en": "Blue T-Shirt", "fr": "T-Shirt Bleu"}
```

```php
// Model:
use Spatie\Translatable\HasTranslations;
class Product extends Model {
    use HasTranslations;
    public array $translatable = ['name', 'description', 'slug'];
}

// Usage (single language for now):
$product->name;               // returns "Blue T-Shirt" (current locale)
$product->getTranslation('name', 'en');  // explicit

// Later, adding French — zero migration needed:
$product->setTranslation('name', 'fr', 'T-Shirt Bleu');
```

Tables that need translatable fields:
- `products` → name, description, slug
- `categories` → name, slug, description
- `brands` → name, description
- `pages` → title, body, meta_title, meta_description
- `coupons` → label (display name)

---

### #7 — Connection Pooling
**Decision: PgBouncer in transaction mode (install on same VPS)**

Even on a single small VPS, PgBouncer is worth it.

Why: PHP-FPM + Octane creates a new DB connection per worker. With 50 Octane workers you'd have 50 open PostgreSQL connections sitting idle. PgBouncer pools these into 10–15 actual DB connections, reducing PostgreSQL memory and overhead significantly.

**Transaction mode** = connection is borrowed only during a SQL transaction, returned immediately after. Most efficient.

```
# On Forge: install via server recipe
sudo apt install pgbouncer

# Laravel connects to PgBouncer (port 6432), not Postgres directly (5432)
DB_HOST=127.0.0.1
DB_PORT=6432   ← PgBouncer port
```

Limitation: `LISTEN/NOTIFY` and prepared statements do not work through PgBouncer in transaction mode — but Laravel doesn't use these by default so no issue.

---

### #8 — Multi-tenancy
**Decision: Single DB, no multi-tenancy**

You are a single seller B2C shop. One database, one schema. Simple.

If you later want to offer the platform as SaaS (other shops on your infrastructure), the path is:
1. Add `tenant_id` column to all tables + global scope in models
2. Or use `spatie/laravel-multitenancy` with schema-per-tenant

**Do not over-engineer this now.** Single DB is the right call.

---

## Summary Table — 0.1 Answers

| # | Decision | Your Answer | Notes |
|---|----------|------------|-------|
| 1 | Engine | **PostgreSQL 16** | Install via Forge on provisioning |
| 2 | ID strategy | **ULID** | `HasUlids` trait on every model |
| 3 | Money storage | **BIGINT cents** | `moneyphp/money` for arithmetic |
| 4 | Timestamps | **UTC** | `APP_TIMEZONE=UTC` in `.env` |
| 5 | Soft deletes | **`deleted_at` on all business tables** | Hard delete only on ephemeral/log tables |
| 6 | Translations | **`spatie/laravel-translatable` (JSON)** | Ready for multilingual, zero cost now |
| 7 | Connection pooling | **PgBouncer transaction mode** | Same VPS, port 6432 |
| 8 | Multi-tenancy | **Single DB** | Revisit only if going SaaS |
