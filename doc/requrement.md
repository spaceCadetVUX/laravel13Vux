# Laravel 13 Backend Architecture Design
> **Project Type:** B2C E-commerce (Online Shop)  
> **Developer:** Solo  
> **Last Updated:** April 2026

---

## Table of Contents
1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Authentication & Authorization](#3-authentication--authorization)
4. [Database Design](#4-database-design)
5. [API Design](#5-api-design)
6. [File & Media Management](#6-file--media-management)
7. [Performance & Scalability](#7-performance--scalability)
8. [Security](#8-security)
9. [Infrastructure & Deployment](#9-infrastructure--deployment)
10. [Admin Panel](#10-admin-panel)
11. [Code Quality & Conventions](#11-code-quality--conventions)
12. [Future Roadmap](#12-future-roadmap)

---

## 1. Project Overview

| Field | Detail |
|---|---|
| Type | B2C E-commerce / Online Shop |
| End Users | Customers (public) + Admin (internal) |
| User Roles | `admin`, `customer` |
| Multi-tenancy | Single shop, single system |
| Scale Target | 1,000 req/min, 1,500 concurrent users |

---

## 2. Technology Stack

| Layer | Technology | Notes |
|---|---|---|
| Framework | Laravel 13 | Latest stable |
| Language | PHP 8.3+ | Required for Laravel 13 |
| Database | PostgreSQL | Complex categories & scale |
| Cache | Redis | Query, session, response cache |
| Queue | Redis + Laravel Horizon | Background jobs |
| Search | Meilisearch + Laravel Scout | Self-hosted on VPS |
| Auth | Laravel Sanctum | Session + token hybrid |
| Social Login | Laravel Socialite | Google OAuth2 |
| Permissions | Spatie Laravel Permission | Role-based |
| Admin Panel | Filament v3 | Free, open source |
| Rich Text | TinyMCE | Product descriptions |
| API Docs | Scribe | Auto-generated from routes |
| Page Cache | spatie/laravel-responsecache | Public page caching |
| Code Format | Laravel Pint | Official Laravel formatter |
| Static Analysis | Larastan | PHPStan for Laravel |
| CI/CD | GitHub Actions | Automated deploy pipeline |
| Containers | Docker Compose | PHP-FPM, Nginx, PG, Redis |
| Backup | PostgreSQL dump → Backblaze B2 | Daily automated backup |
| Payment | VNPay | Planned — future sprint |

---

## 3. Authentication & Authorization

### Strategy
- **Package:** Laravel Sanctum
- **Admin:** Session-based (Filament panel)
- **Customer API:** Token-based (SPA / mobile ready)
- **Social Login:** Google via Laravel Socialite
- **2FA:** Not required at launch

### Roles & Permissions
- Managed by **Spatie Laravel Permission**
- Two roles: `admin`, `customer`
- Admin has full system access
- Customer access is scoped to their own data only

### Public vs Protected Endpoints

| Access | Endpoints |
|---|---|
| Public (no auth) | Product list, product detail, categories, search |
| Protected (customer) | Cart, checkout, orders, profile |
| Protected (admin) | All admin panel routes, admin API |

---

## 4. Database Design

### Engine
- **PostgreSQL** (primary database)
- **Redis** (cache, queue, sessions)

### Key Principles
- Soft delete enabled on all critical tables (`products`, `orders`, `users`, `categories`)
- Audit logging via **spatie/laravel-activitylog**
- PII fields encrypted using Laravel's built-in `encrypt()`
- UUID primary keys on user-facing tables (security + scalability)

### Core Entities

```
users
├── id (uuid)
├── name
├── email (encrypted)
├── phone (encrypted)
├── role: admin | customer
├── google_id
├── email_verified_at
├── deleted_at (soft delete)
└── timestamps

categories
├── id
├── parent_id (self-referencing, nested categories)
├── name
├── slug
├── description
├── image_path
├── sort_order
├── is_active
├── deleted_at
└── timestamps

products
├── id (uuid)
├── category_id
├── name
├── slug
├── sku
├── short_description
├── description (richtext / TinyMCE)
├── price
├── sale_price
├── stock_quantity
├── is_active
├── deleted_at
└── timestamps

product_images
├── id
├── product_id
├── path
├── alt_text
├── sort_order
└── timestamps

product_videos
├── id
├── product_id
├── path
├── thumbnail_path
└── timestamps

orders
├── id (uuid)
├── user_id
├── status: pending | processing | shipped | delivered | cancelled
├── total_amount
├── shipping_address (encrypted JSON)
├── note
├── deleted_at
└── timestamps

order_items
├── id
├── order_id
├── product_id
├── quantity
├── unit_price
└── timestamps

addresses
├── id
├── user_id
├── label: home | office | other
├── full_name
├── phone (encrypted)
├── address_line (encrypted)
├── city
├── district
├── ward
├── is_default
└── timestamps

carts
├── id
├── user_id (nullable — guest support)
├── session_id (for guest)
└── timestamps

cart_items
├── id
├── cart_id
├── product_id
├── quantity
└── timestamps
```

---

## 5. API Design

### Architecture
- **Style:** REST API (mixed with Blade for admin)
- **Versioning:** `/api/v1/` prefix from day one
- **Auth:** Bearer token via Sanctum for customer API

### Response Envelope

```json
{
  "success": true,
  "data": {},
  "message": "OK",
  "errors": null,
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150
  }
}
```

### Route Map

#### Public Routes `/api/v1/`
```
GET    /products                  List products (paginated)
GET    /products/{slug}           Product detail
GET    /categories                List categories
GET    /categories/{slug}         Category with products
GET    /search?q=                 Full-text search (Meilisearch)
```

#### Customer Routes `/api/v1/` (Sanctum auth required)
```
POST   /auth/register
POST   /auth/login
POST   /auth/logout
GET    /auth/me
POST   /auth/google               Google OAuth callback

GET    /cart                      Get cart
POST   /cart/items                Add item to cart
PUT    /cart/items/{id}           Update quantity
DELETE /cart/items/{id}           Remove item

POST   /orders                    Place order
GET    /orders                    Order history
GET    /orders/{id}               Order detail

GET    /addresses                 List addresses
POST   /addresses                 Create address
PUT    /addresses/{id}            Update address
DELETE /addresses/{id}            Delete address
```

#### Admin Routes (Filament panel — session auth)
```
/admin/*                          Filament v3 panel (all CRUD)
```

### API Documentation
- **Tool:** Scribe (auto-generated)
- **Output:** `/docs` route in local/staging environments
- Postman collection exported and committed to repo

---

## 6. File & Media Management

| Setting | Value |
|---|---|
| Upload side | Admin only |
| Storage | Local VPS (`storage/app/public`) |
| Max file size | 10MB |
| Allowed types | Images: jpg, png, webp — Video: mp4 |
| Image resize | Not required at launch |
| Symlink | `php artisan storage:link` |
| Serving | Nginx serves `/storage` directly |

### Upload Flow
```
Admin uploads file
→ Validated in FormRequest (size, mime type)
→ Stored to storage/app/public/products/{year}/{month}/
→ Path saved to DB (product_images / product_videos)
→ Public URL returned via Storage::url()
```

---

## 7. Performance & Scalability

### Caching Strategy (Redis)

| Cache Type | Target | TTL |
|---|---|---|
| Route cache | All routes | Production only |
| Config cache | All config | Production only |
| Query cache | Category tree, product listings | 10 min |
| Response cache | Public pages (homepage, product detail) | 5 min |
| Session cache | User sessions | 120 min |

### Queue & Workers
- **Driver:** Redis
- **Monitor:** Laravel Horizon (`/horizon` — admin only)
- **Queued jobs at launch:**
  - Order confirmation email
  - Low stock alert notification
  - Activity log writes (async)

### Search
- **Engine:** Meilisearch (self-hosted, same VPS)
- **Package:** Laravel Scout
- **Indexed models:** `Product`, `Category`
- **Searchable fields:** name, description, SKU, category name

### Concurrency Target
- 1,500 concurrent users
- 1,000 req/min
- PHP-FPM pool tuned: `pm.max_children = 50`
- Nginx worker tuned to match CPU cores
- Redis connection pooling via persistent connections

---

## 8. Security

### Encryption
- Customer PII fields encrypted at rest (email, phone, address)
- Laravel `encrypt()` / `decrypt()` helpers
- App key rotated on environment setup

### API Security
- Rate limiting: `throttle:60,1` on all API routes
- Stricter limit on auth routes: `throttle:10,1`
- CORS configured via `config/cors.php`
- CSRF protection on all web/admin routes

### Headers (Nginx level)
```nginx
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'
```

### Other
- `.env` never committed to Git (`.gitignore` enforced)
- Secrets managed via GitHub Actions secrets
- Admin panel IP whitelist (optional but recommended)
- Regular `composer audit` in CI pipeline

---

## 9. Infrastructure & Deployment

### Server Setup (Docker Compose)

```yaml
Services:
  - nginx          (reverse proxy, static files)
  - php-fpm        (Laravel app, PHP 8.3)
  - postgres       (primary database)
  - redis          (cache, queue, sessions)
  - meilisearch    (full-text search)
  - horizon        (queue worker monitor)
  - scheduler      (cron: php artisan schedule:run)
```

### Environments

| Environment | Purpose | URL |
|---|---|---|
| Local | Development | `localhost` |
| Staging | QA / testing | `staging.yourdomain.com` |
| Production | Live | `yourdomain.com` |

### GitHub Actions CI/CD Pipeline

```
Push to main branch
→ Run Laravel Pint (code style check)
→ Run Larastan (static analysis)
→ Run PHPUnit tests
→ SSH into VPS
→ git pull
→ composer install --no-dev
→ php artisan migrate --force
→ php artisan config:cache
→ php artisan route:cache
→ php artisan view:cache
→ Restart php-fpm / Horizon
```

### Backup Strategy

| Target | Method | Frequency | Destination |
|---|---|---|---|
| PostgreSQL | `pg_dump` script | Daily 2AM | Backblaze B2 |
| Uploaded files | `rclone sync` | Daily 3AM | Backblaze B2 |
| Retention | — | 30 days rolling | — |

---

## 10. Admin Panel

### Filament v3
- **License:** Free / Open Source (MIT)
- **URL:** `/admin`
- **Auth:** Session-based, admin role only

### Panel Resources

| Resource | Features |
|---|---|
| Products | Full CRUD, TinyMCE, image/video upload, bulk actions |
| Categories | Nested tree, drag-and-drop sort |
| Orders | Status management, order detail view, export |
| Customers | View, soft delete, search |
| Activity Log | Read-only audit log viewer |
| Media | File manager for uploaded assets |

---

## 11. Code Quality & Conventions

### Tools
| Tool | Purpose |
|---|---|
| Laravel Pint | Code formatting (PSR-12 based) |
| Larastan (level 6) | Static analysis |
| PHPUnit | Feature & unit testing |
| GitHub Actions | Runs all checks on every push |

### Folder Structure Convention
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          ← versioned API controllers
│   │   └── Admin/           ← admin-specific controllers
│   ├── Requests/            ← FormRequests per action
│   ├── Resources/           ← API Resources (transformers)
│   └── Middleware/
├── Models/
├── Services/                ← Business logic layer
├── Repositories/            ← DB query layer (optional)
├── Jobs/                    ← Queued jobs
├── Events/ & Listeners/
├── Policies/                ← Authorization policies
└── Enums/                   ← PHP 8.1+ Enums (OrderStatus, etc.)
```

### Naming Conventions
- Controllers: `ProductController`, `OrderController`
- Services: `OrderService`, `CartService`
- FormRequests: `StoreProductRequest`, `UpdateOrderRequest`
- API Resources: `ProductResource`, `OrderCollection`
- Jobs: `SendOrderConfirmationEmail`, `SyncProductToSearch`
- Enums: `OrderStatus`, `UserRole`

---

## 12. Future Roadmap

| Feature | Priority | Notes |
|---|---|---|
| VNPay Payment Gateway | High | Next sprint after launch |
| Email Notifications | High | Order confirm, shipping update |
| Push Notifications | Medium | Firebase FCM |
| Product Reviews & Ratings | Medium | — |
| Discount / Coupon System | Medium | — |
| Inventory Management | Medium | Low stock alerts |
| AWS Migration | Low | When VPS hits limits |
| Mobile App API | Low | Already API-ready |
| Multi-tenant / Multi-shop | Low | Architecture supports it |
| Analytics Dashboard | Low | Laravel + Filament widgets |

---

## Composer Packages Reference

```bash
# Core
composer require laravel/sanctum
composer require laravel/socialite
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-responsecache

# Admin
composer require filament/filament

# Search
composer require laravel/scout
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle

# Queue Monitor
composer require laravel/horizon

# API Docs
composer require knuckleswtf/scribe --dev

# Code Quality
composer require laravel/pint --dev
composer require nunomaduro/larastan --dev
```

---

*This document serves as the single source of truth for backend architecture decisions. Update it as the project evolves.*