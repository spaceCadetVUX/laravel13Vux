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

## Dev ↔ Production Parity

- Local: Laragon + PostgreSQL 16 (manual binary) OR `postgres:16` in Docker Compose
- Production: Forge VPS + PostgreSQL 16 (auto) + PgBouncer
- Both must run **PostgreSQL 16** — never mix major versions
