# Backend Build Plan
> **For:** Claude Code CLI (AI-assisted development)
> **Project:** Laravel 13 B2C E-commerce + Blog
> **PHP:** 8.3+ | **DB:** PostgreSQL | **Admin:** Filament v3
> **Rule:** One sprint = one focused task. Complete and test before next sprint.
> **Last Updated:** April 2026

---

## How to Use This File

1. Open Claude Code CLI in `/backend`
2. Paste the sprint prompt exactly as written
3. Claude Code reads `CLAUDE.md` automatically for context
4. Test the sprint output before moving to the next
5. Commit after each passing sprint

```bash
# Start each sprint with
cd backend
claude
# Then paste the sprint prompt
```

---

## Sprint Index

| Sprint | Task | Dependencies |
|---|---|---|
| S00 | Laravel 13 project init | None |
| S01 | Docker Compose + environment | S00 |
| S02 | Database + Redis connection verify | S01 |
| S03 | Migrations — Auth tables | S02 |
| S04 | Migrations — Catalog tables | S03 |
| S05 | Migrations — Commerce tables | S04 |
| S06 | Migrations — Blog tables | S05 |
| S07 | Migrations — SEO & GEO tables | S06 |
| S08 | Migrations — Shared + run all | S07 |
| S09 | Core seeders | S08 |
| S10 | Enums | S09 |
| S11 | Models — Auth | S10 |
| S12 | Models — Catalog | S11 |
| S13 | Models — Commerce | S12 |
| S14 | Models — Blog | S13 |
| S15 | Models — SEO & GEO | S14 |
| S16 | Models — Shared + morphMap | S15 |
| S17 | SEO Traits | S16 |
| S18 | AppServiceProvider + morphMap | S17 |
| S19 | Auth — Sanctum + Google OAuth setup | S18 |
| S20 | Auth — Register + Login API | S19 |
| S21 | Auth — Google OAuth API | S20 |
| S22 | Auth — Me + Update + Logout API | S21 |
| S23 | Filament install + admin auth | S22 |
| S24 | Filament — Category resource | S23 |
| S25 | Filament — Product resource | S24 |
| S26 | Filament — Blog resource | S25 |
| S27 | Filament — Order resource | S26 |
| S28 | Filament — SEO Meta resource | S27 |
| S29 | Filament — GEO Profile resource | S28 |
| S30 | Filament — JSON-LD resource | S29 |
| S31 | Filament — Redirects resource | S30 |
| S32 | Filament — Sitemap resource | S31 |
| S33 | Observers — Product + Category | S32 |
| S34 | Observers — BlogPost + Redirect | S33 |
| S35 | Jobs — SEO sync jobs | S34 |
| S36 | Services — JsonldService | S35 |
| S37 | Services — SitemapService | S36 |
| S38 | Services — LlmsGeneratorService | S37 |
| S39 | Services — RedirectCacheService | S38 |
| S40 | Artisan commands — sitemap + llms | S39 |
| S41 | Web routes — Sitemap XML | S40 |
| S42 | Web routes — LLMs txt | S41 |
| S43 | Web routes — Health check | S42 |
| S44 | Middleware — HandleRedirects | S43 |
| S45 | API — Category endpoints | S44 |
| S46 | API — Product list + detail | S45 |
| S47 | API — Search endpoint | S46 |
| S48 | API — Cart (guest + auth) | S47 |
| S49 | API — Orders | S48 |
| S50 | API — Addresses | S49 |
| S51 | API — Blog endpoints | S50 |
| S52 | API — Blog comments | S51 |
| S53 | Horizon + queue config | S52 |
| S54 | Scheduler — cart prune + sitemap | S53 |
| S55 | Scribe API docs | S54 |
| S56 | Feature tests — Auth | S55 |
| S57 | Feature tests — Products + Categories | S56 |
| S58 | Feature tests — Cart + Orders | S57 |
| S59 | Feature tests — Blog | S58 |
| S60 | Feature tests — SEO routes | S59 |

---

## S00 — Laravel 13 Project Init

```
Create a new Laravel 13 project inside a folder called `backend` at the monorepo root.

Requirements:
- Use composer create-project laravel/laravel backend "^13.0"
- PHP 8.3 minimum
- Remove unused default files: welcome blade, default routes in web.php (keep file, clear content)
- Add to .gitignore: .env, /vendor, /node_modules, storage/logs/*.log
- Verify php artisan --version returns Laravel 13.x

Do not install any packages yet. Do not run migrations yet.
Output: working Laravel 13 installation in /backend
```

---

## S01 — Docker Compose + Environment

```
Create the full Docker Compose stack for this Laravel 13 + Nuxt 3 monorepo project.

Stack required (docker-compose.yml at repo root):
- nginx (port 80 → backend, port 3000 → frontend later)
- php-fpm (PHP 8.3, Laravel app at /backend)
- postgres (port 5432, db: app, user: app, password: secret)
- redis (port 6379)
- meilisearch (port 7700, MEILI_NO_ANALYTICS=true)
- horizon (shares php-fpm image, runs php artisan horizon)
- scheduler (shares php-fpm image, runs every minute: php artisan schedule:run)

Create these files:
1. docker-compose.yml (repo root)
2. docker/nginx/default.conf (server block for Laravel, proxy /api and /admin to php-fpm)
3. docker/php/Dockerfile (PHP 8.3-fpm, install pgsql, redis, zip extensions)
4. docker/php/php.ini (upload_max_filesize=10M, memory_limit=256M, max_execution_time=60)
5. docker/scripts/entrypoint.sh (php artisan migrate --force, storage:link, config:cache on boot)
6. backend/.env (copy from .env.example with Docker service hostnames)
7. backend/.env.example (all required keys — see list below)

Required .env keys:
APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL,
DB_CONNECTION=pgsql, DB_HOST=postgres, DB_PORT=5432, DB_DATABASE=app, DB_USERNAME=app, DB_PASSWORD=secret,
REDIS_HOST=redis, REDIS_PORT=6379, REDIS_PASSWORD=null,
MEILISEARCH_HOST=http://meilisearch:7700, MEILISEARCH_KEY=,
QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=redis,
GOOGLE_CLIENT_ID=, GOOGLE_CLIENT_SECRET=, GOOGLE_REDIRECT_URI=,
MAIL_MAILER=log,
FRONTEND_URL=http://localhost:3000,
SANCTUM_STATEFUL_DOMAINS=localhost:3000

Output: docker compose up -d runs without errors, all services healthy
Test: docker compose ps shows all containers running
```

---

## S02 — Database + Redis Connection Verify

```
Verify PostgreSQL and Redis connections are working inside the Laravel backend.

Tasks:
1. Install required composer packages:
   - doctrine/dbal (for PostgreSQL column operations in migrations)

2. Update backend/config/database.php:
   - Set default connection to pgsql
   - Add 'search_path' => 'public' to pgsql options
   - Set 'charset' => 'utf8'

3. Update backend/config/cache.php:
   - Default store: redis

4. Update backend/config/session.php:
   - Driver: redis

5. Update backend/config/queue.php:
   - Default connection: redis

6. Run and verify:
   - php artisan db:show (should connect to PostgreSQL)
   - php artisan tinker → Cache::set('test', 'ok') → Cache::get('test') === 'ok'

Output: PostgreSQL and Redis both connected and verified
Test: php artisan db:show returns PostgreSQL connection details without errors
```

---

## S03 — Migrations: Auth Tables

```
Create migrations for the Auth group only. Run them after creation.

Reference: ERD.md section 3.1 Auth & Permissions

Migrations to create (in this exact order):
1. 0001_create_users_table
   - id: uuid PK
   - name: string(255) NOT NULL
   - email: text NOT NULL unique
   - phone: text nullable
   - password: string(255) nullable
   - role: enum(['admin','customer']) default 'customer'
   - google_id: string(255) nullable unique
   - email_verified_at: timestamp nullable
   - remember_token: string(100) nullable
   - deleted_at: timestamp nullable (softDeletes)
   - timestamps
   - indexes: role, deleted_at

2. 0002_create_password_reset_tokens_table
   - Laravel default schema (email PK, token, created_at)

3. 0003_create_personal_access_tokens_table
   - Laravel Sanctum default schema
   - IMPORTANT: change tokenable_id to string(36) not morphs() default

4. 0004_create_permission_tables
   - Spatie Laravel Permission default schema
   - IMPORTANT: change model_id to string(36) in model_has_roles and model_has_permissions

5. 0005_create_addresses_table
   - id: uuid PK
   - user_id: foreignUuid → users.id cascadeOnDelete
   - label: enum(['home','office','other']) default 'home'
   - full_name: string(255) NOT NULL
   - phone: text NOT NULL
   - address_line: text NOT NULL
   - city, district, ward: string(100) NOT NULL
   - is_default: boolean default false
   - timestamps
   - indexes: user_id, is_default

Run: php artisan migrate
Verify: php artisan db:table users

Output: 5 migration files created, all tables exist in PostgreSQL
```

---

## S04 — Migrations: Catalog Tables

```
Create migrations for the Product Catalog group only.

Reference: ERD.md section 3.2 Product Catalog

Migrations to create:
1. 0006_create_categories_table
   - id: bigIncrements PK
   - parent_id: unsignedBigInteger nullable (FK added after table creation)
   - name: string(255) NOT NULL
   - slug: string(255) NOT NULL unique
   - description: text nullable
   - image_path: string(500) nullable
   - sort_order: integer default 0
   - is_active: boolean default true
   - deleted_at: softDeletes
   - timestamps
   - indexes: parent_id, is_active, sort_order
   - Add FK: parent_id → categories.id nullOnDelete AFTER table creation

2. 0007_create_products_table
   - id: uuid PK
   - category_id: unsignedBigInteger nullable FK → categories.id nullOnDelete
   - name: string(255) NOT NULL
   - slug: string(255) NOT NULL unique
   - sku: string(100) NOT NULL unique
   - short_description: text nullable
   - description: longText nullable
   - price: decimal(12,2) NOT NULL
   - sale_price: decimal(12,2) nullable
   - stock_quantity: integer default 0
   - is_active: boolean default true
   - deleted_at: softDeletes
   - timestamps
   - indexes: category_id, is_active, deleted_at

3. 0008_create_product_images_table
   - id: bigIncrements PK
   - product_id: uuid FK → products.id cascadeOnDelete
   - path: string(500) NOT NULL
   - alt_text: string(255) nullable
   - sort_order: integer default 0
   - timestamps
   - indexes: product_id, sort_order

4. 0009_create_product_videos_table
   - id: bigIncrements PK
   - product_id: uuid FK → products.id cascadeOnDelete
   - path: string(500) NOT NULL
   - thumbnail_path: string(500) nullable
   - timestamps
   - index: product_id

Run: php artisan migrate
Verify: php artisan db:table products

Output: 4 migration files, all catalog tables exist
```

---

## S05 — Migrations: Commerce Tables

```
Create migrations for the Cart & Orders group only.

Reference: ERD.md section 3.3 Cart & Orders

Migrations to create:
1. 0010_create_carts_table
   - id: uuid PK
   - user_id: uuid nullable FK → users.id cascadeOnDelete
   - session_id: string(255) nullable
   - expires_at: timestamp NOT NULL
   - timestamps
   - indexes: user_id, session_id, expires_at

2. 0011_create_cart_items_table
   - id: bigIncrements PK
   - cart_id: uuid FK → carts.id cascadeOnDelete
   - product_id: uuid FK → products.id cascadeOnDelete
   - quantity: integer default 1
   - timestamps
   - unique: [cart_id, product_id]
   - indexes: cart_id, product_id

3. 0012_create_orders_table
   - id: uuid PK
   - user_id: uuid nullable FK → users.id nullOnDelete
   - status: enum(['pending','processing','shipped','delivered','cancelled']) default 'pending'
   - total_amount: decimal(12,2) NOT NULL
   - shipping_address: jsonb NOT NULL
   - payment_method: string(50) nullable
   - payment_status: enum(['unpaid','paid','refunded']) default 'unpaid'
   - note: text nullable
   - deleted_at: softDeletes
   - timestamps
   - indexes: user_id, status, payment_status, created_at, deleted_at

4. 0013_create_order_items_table
   - id: bigIncrements PK
   - order_id: uuid FK → orders.id cascadeOnDelete
   - product_id: uuid nullable FK → products.id nullOnDelete
   - product_name: string(255) NOT NULL
   - product_sku: string(100) NOT NULL
   - quantity: integer NOT NULL
   - unit_price: decimal(12,2) NOT NULL
   - timestamps
   - indexes: order_id, product_id

Run: php artisan migrate
Verify: php artisan db:table carts

Output: 4 migration files, all commerce tables exist
```

---

## S06 — Migrations: Blog Tables

```
Create migrations for the Blog group only.

Reference: ERD.md section 3.4 Blog

Migrations to create:
1. 0014_create_blog_categories_table
   - id: bigIncrements PK
   - parent_id: unsignedBigInteger nullable
   - name: string(255) NOT NULL
   - slug: string(255) NOT NULL unique
   - description: text nullable
   - is_active: boolean default true
   - timestamps
   - indexes: parent_id, is_active
   - FK parent_id → blog_categories.id nullOnDelete (added after table)

2. 0015_create_blog_posts_table
   - id: uuid PK
   - author_id: uuid nullable FK → users.id nullOnDelete
   - blog_category_id: unsignedBigInteger nullable FK → blog_categories.id nullOnDelete
   - title: string(255) NOT NULL
   - slug: string(255) NOT NULL unique
   - excerpt: text nullable
   - content: longText NOT NULL
   - featured_image: string(500) nullable
   - status: enum(['draft','published','archived']) default 'draft'
   - published_at: timestamp nullable
   - deleted_at: softDeletes
   - timestamps
   - indexes: author_id, blog_category_id, status, published_at, deleted_at

3. 0016_create_blog_tags_table
   - id: bigIncrements PK
   - name: string(100) NOT NULL unique
   - slug: string(100) NOT NULL unique
   - timestamps

4. 0017_create_blog_post_tag_table
   - blog_post_id: uuid FK → blog_posts.id cascadeOnDelete
   - blog_tag_id: unsignedBigInteger FK → blog_tags.id cascadeOnDelete
   - primary: [blog_post_id, blog_tag_id]

5. 0018_create_blog_comments_table
   - id: bigIncrements PK
   - blog_post_id: uuid FK → blog_posts.id cascadeOnDelete
   - user_id: uuid nullable FK → users.id nullOnDelete
   - body: text NOT NULL
   - is_approved: boolean default false
   - timestamps
   - indexes: blog_post_id, user_id, is_approved

Run: php artisan migrate
Verify: php artisan db:table blog_posts

Output: 5 migration files, all blog tables exist
```

---

## S07 — Migrations: SEO & GEO Tables

```
Create migrations for the SEO & GEO group only.

Reference: ERD.md section 3.5 SEO & GEO
CRITICAL: ALL model_id columns in this section MUST be string(36) — never uuid()

Migrations to create:
1. 0019_create_seo_meta_table
   - id: bigIncrements PK
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36) not uuid
   - meta_title: string(160) nullable
   - meta_description: string(320) nullable
   - meta_keywords: string(500) nullable
   - og_title: string(160) nullable
   - og_description: string(320) nullable
   - og_image: string(500) nullable
   - og_type: string(50) nullable default 'website'
   - twitter_card: string(50) nullable default 'summary_large_image'
   - twitter_title: string(160) nullable
   - twitter_description: string(320) nullable
   - canonical_url: string(500) nullable
   - robots: string(100) nullable default 'index, follow'
   - timestamps
   - unique: [model_type, model_id]

2. 0020_create_geo_entity_profiles_table
   - id: bigIncrements PK
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36)
   - ai_summary: text nullable
   - key_facts: jsonb nullable
   - faq: jsonb nullable
   - use_cases: text nullable
   - target_audience: string(255) nullable
   - llm_context_hint: text nullable
   - timestamps
   - unique: [model_type, model_id]

3. 0021_create_jsonld_templates_table
   - id: bigIncrements PK
   - schema_type: string(100) NOT NULL unique
   - label: string(100) NOT NULL
   - template: jsonb NOT NULL
   - placeholders: jsonb nullable
   - is_auto_generated: boolean default true
   - timestamps

4. 0022_create_jsonld_schemas_table
   - id: bigIncrements PK
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36)
   - schema_type: string(100) NOT NULL
   - label: string(100) nullable
   - payload: jsonb NOT NULL
   - is_active: boolean default true
   - is_auto_generated: boolean default true
   - sort_order: integer default 0
   - timestamps
   - index: [model_type, model_id], schema_type, is_active

5. 0023_create_llms_documents_table
   - id: bigIncrements PK
   - name: string(100) NOT NULL unique
   - slug: string(100) NOT NULL unique
   - title: string(255) NOT NULL
   - description: text nullable
   - scope: string(50) default 'full'
   - model_type: string(255) nullable
   - entry_count: integer default 0
   - last_generated_at: timestamp nullable
   - is_active: boolean default true
   - timestamps

6. 0024_create_llms_entries_table
   - id: bigIncrements PK
   - llms_document_id: unsignedBigInteger FK → llms_documents.id cascadeOnDelete
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36)
   - title: string(255) NOT NULL
   - url: string(500) NOT NULL
   - summary: text nullable
   - key_facts_text: text nullable
   - faq_text: text nullable
   - is_active: boolean default true
   - updated_at: timestamp
   - unique: [model_type, model_id] per llms_document_id
   - indexes: llms_document_id, is_active

7. 0025_create_redirects_table
   - id: bigIncrements PK
   - from_path: string(500) NOT NULL unique
   - to_path: string(500) NOT NULL
   - type: smallInteger default 301
   - hits: integer default 0
   - cache_version: integer default 1
   - is_active: boolean default true
   - timestamps
   - index: is_active

8. 0026_create_sitemap_indexes_table
   - id: bigIncrements PK
   - name: string(100) NOT NULL unique
   - filename: string(100) NOT NULL unique
   - url: string(500) NOT NULL
   - entry_count: integer default 0
   - last_generated_at: timestamp nullable
   - is_active: boolean default true
   - timestamps

9. 0027_create_sitemap_entries_table
   - id: bigIncrements PK
   - sitemap_index_id: unsignedBigInteger FK → sitemap_indexes.id cascadeOnDelete
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36)
   - url: string(500) NOT NULL
   - changefreq: string(20) nullable default 'weekly'
   - priority: decimal(2,1) nullable default 0.8
   - last_modified: timestamp nullable
   - is_active: boolean default true
   - updated_at: timestamp
   - unique: [model_type, model_id] per sitemap_index_id
   - indexes: sitemap_index_id, is_active, last_modified

Run: php artisan migrate
Verify: php artisan db:table seo_meta

Output: 9 migration files, all SEO/GEO tables exist
```

---

## S08 — Migrations: Shared Tables + Run All Fresh

```
Create the final shared infrastructure migrations, then verify the entire schema.

Migrations to create:
1. 0028_create_media_table
   - id: bigIncrements PK
   - model_type: string(255) NOT NULL
   - model_id: string(36) NOT NULL ← varchar(36)
   - collection: string(100) default 'default'
   - path: string(500) NOT NULL
   - disk: string(50) default 'public'
   - mime_type: string(100) nullable
   - size: unsignedBigInteger nullable
   - timestamps
   - index: [model_type, model_id], collection

2. 0029_create_activity_logs_table
   - id: bigIncrements PK
   - log_name: string(255) nullable
   - description: text NOT NULL
   - subject_type: string(255) nullable
   - subject_id: string(36) nullable ← varchar(36)
   - causer_type: string(255) nullable
   - causer_id: string(36) nullable ← varchar(36)
   - properties: jsonb nullable
   - timestamps
   - indexes: [subject_type, subject_id], [causer_type, causer_id], log_name, created_at

3. 0030_create_cache_table
   - Run: php artisan cache:table
   - (Laravel generates this automatically)

4. 0031_create_sessions_table
   - Run: php artisan session:table
   - (Laravel generates this automatically)

After all migrations created:
- Run: php artisan migrate:fresh
- Verify ALL 31 tables exist: php artisan db:show --counts
- Total expected tables: 31 (not counting Laravel internal tables)

Output: All 31 tables exist, migrate:fresh runs without errors
Test: php artisan db:show --counts shows all tables with 0 rows
```

---

## S09 — Core Seeders

```
Create and run the essential seeders that bootstrap the system.

Reference: FOLDER_STRUCTURE.md database/seeders/

Seeders to create:
1. RoleSeeder
   - Creates roles: 'admin' (guard: web), 'customer' (guard: web)
   - Uses Spatie Permission: Role::firstOrCreate(['name' => 'admin'])

2. AdminUserSeeder
   - Creates one admin user:
     email: admin@example.com, password: password (hashed)
     name: Admin, role: admin
   - Assigns 'admin' role via Spatie

3. JsonldTemplateSeeder
   - Seeds base JSON-LD templates for:
     a) Product schema (schema.org/Product) with placeholders:
        {{product.name}}, {{product.slug}}, {{product.short_description}},
        {{product.sku}}, {{product.price}}, {{product.first_image_url}},
        {{product.canonical_url}}, {{product.availability}}
     b) Article schema (schema.org/Article) with placeholders:
        {{blog_post.title}}, {{blog_post.slug}}, {{blog_post.excerpt}},
        {{blog_post.featured_image}}, {{blog_post.published_at}},
        {{blog_post.author_name}}, {{blog_post.canonical_url}}
     c) CollectionPage (Category) with placeholders:
        {{category.name}}, {{category.slug}}, {{category.canonical_url}}
     d) BreadcrumbList (shared) — generic template
     e) FAQPage (shared) — generic template
     f) WebSite (static) — site-wide schema
     g) Organization (static) — company schema

4. SitemapIndexSeeder
   - Seeds sitemap_indexes rows:
     {name: 'products', filename: 'sitemap-products.xml', url: APP_URL/sitemap-products.xml}
     {name: 'blog', filename: 'sitemap-blog.xml', url: APP_URL/sitemap-blog.xml}
     {name: 'categories', filename: 'sitemap-categories.xml', url: APP_URL/sitemap-categories.xml}

5. LlmsDocumentSeeder
   - Seeds llms_documents rows:
     {name: 'root', slug: 'root', scope: 'index', model_type: null, title: 'Site Index'}
     {name: 'products', slug: 'products', scope: 'full', model_type: 'App\Models\Product'}
     {name: 'blog', slug: 'blog', scope: 'full', model_type: 'App\Models\BlogPost'}
     {name: 'categories', slug: 'categories', scope: 'full', model_type: 'App\Models\Category'}

6. DatabaseSeeder (orchestrator)
   - Calls in order: RoleSeeder, AdminUserSeeder, JsonldTemplateSeeder,
     SitemapIndexSeeder, LlmsDocumentSeeder

Run: php artisan db:seed
Verify: php artisan tinker → User::count(), Role::count()

Output: All seeders run without errors, admin user exists, templates seeded
```

---

## S10 — Enums

```
Create all PHP 8.3 backed Enums in app/Enums/.

Reference: FOLDER_STRUCTURE.md app/Enums/
Each enum must be a backed enum (string or int).

Enums to create:
1. UserRole (string)
   - Admin = 'admin'
   - Customer = 'customer'

2. OrderStatus (string)
   - Pending = 'pending'
   - Processing = 'processing'
   - Shipped = 'shipped'
   - Delivered = 'delivered'
   - Cancelled = 'cancelled'

3. PaymentStatus (string)
   - Unpaid = 'unpaid'
   - Paid = 'paid'
   - Refunded = 'refunded'

4. AddressLabel (string)
   - Home = 'home'
   - Office = 'office'
   - Other = 'other'

5. BlogPostStatus (string)
   - Draft = 'draft'
   - Published = 'published'
   - Archived = 'archived'

6. RedirectType (int)
   - Permanent = 301
   - Temporary = 302

7. OgType (string)
   - Website = 'website'
   - Article = 'article'
   - Product = 'product'

8. JsonldSchemaType (string)
   - Product = 'Product'
   - Article = 'Article'
   - BreadcrumbList = 'BreadcrumbList'
   - FaqPage = 'FAQPage'
   - Organization = 'Organization'
   - WebSite = 'WebSite'
   - CollectionPage = 'CollectionPage'
   - Blog = 'Blog'

9. SitemapChangefreq (string)
   - Always = 'always'
   - Hourly = 'hourly'
   - Daily = 'daily'
   - Weekly = 'weekly'
   - Monthly = 'monthly'
   - Yearly = 'yearly'
   - Never = 'never'

10. LlmsScope (string)
    - Index = 'index'
    - Full = 'full'

Output: 10 enum files in app/Enums/, all backed with correct cases
Test: php artisan tinker → App\Enums\OrderStatus::Pending->value === 'pending'
```

---

## S11 — Models: Auth

```
Create Eloquent models for the Auth group.

Reference: ERD.md section 3.1, FOLDER_STRUCTURE.md app/Models/

Models to create:
1. User (update existing app/Models/User.php)
   - Use HasFactory, Notifiable, HasRoles (Spatie), SoftDeletes
   - fillable: name, email, phone, password, role, google_id
   - hidden: password, remember_token
   - casts: email_verified_at → datetime, deleted_at → datetime,
            role → App\Enums\UserRole
   - Relationships:
     hasMany Address (addresses)
     hasMany Cart (carts)
     hasMany Order (orders)
     hasMany BlogPost (blogPosts) via author_id
     hasMany BlogComment (blogComments)
   - Encrypted attributes (use Laravel encryption):
     getEmailAttribute / setEmailAttribute
     getPhoneAttribute / setPhoneAttribute

2. Address (app/Models/Address.php)
   - Use HasFactory
   - keyType: string (uuid)
   - incrementing: false
   - fillable: user_id, label, full_name, phone, address_line, city, district, ward, is_default
   - casts: label → App\Enums\AddressLabel, is_default → boolean
   - Encrypted: phone, address_line
   - Relationships: belongsTo User

Output: 2 model files, all relationships defined, encrypted attributes work
Test: php artisan tinker → User::factory()->create() works
```

---

## S12 — Models: Catalog

```
Create Eloquent models for the Product Catalog group.

Models to create:
1. Category (app/Models/Category.php)
   - Use HasFactory, SoftDeletes
   - keyType: int, incrementing: true
   - fillable: parent_id, name, slug, description, image_path, sort_order, is_active
   - casts: is_active → boolean, deleted_at → datetime
   - Relationships:
     belongsTo Category (parent) via parent_id
     hasMany Category (children) via parent_id
     hasMany Product (products)
   - Scope: scopeActive($query) → where is_active = true

2. Product (app/Models/Product.php)
   - Use HasFactory, SoftDeletes, Searchable (Laravel Scout)
   - keyType: string (uuid), incrementing: false
   - fillable: category_id, name, slug, sku, short_description, description,
               price, sale_price, stock_quantity, is_active
   - casts: price → decimal:2, sale_price → decimal:2,
            is_active → boolean, deleted_at → datetime
   - Relationships:
     belongsTo Category
     hasMany ProductImage (images)
     hasMany ProductVideo (videos)
     hasMany CartItem (cartItems)
     hasMany OrderItem (orderItems)
   - Scope: scopeActive($query)
   - toSearchableArray(): return name, sku, short_description, category name, price, is_active

3. ProductImage (app/Models/ProductImage.php)
   - fillable: product_id, path, alt_text, sort_order
   - casts: sort_order → integer
   - Relationship: belongsTo Product
   - Accessor: getUrlAttribute() → Storage::url($this->path)

4. ProductVideo (app/Models/ProductVideo.php)
   - fillable: product_id, path, thumbnail_path
   - Relationship: belongsTo Product
   - Accessors: getUrlAttribute(), getThumbnailUrlAttribute()

Output: 4 model files, Scout searchable on Product
Test: php artisan tinker → Product::factory()->create() works
```

---

## S13 — Models: Commerce

```
Create Eloquent models for the Cart & Orders group.

Models to create:
1. Cart (app/Models/Cart.php)
   - keyType: string (uuid), incrementing: false
   - fillable: user_id, session_id, expires_at
   - casts: expires_at → datetime
   - Relationships:
     belongsTo User (nullable)
     hasMany CartItem (items)
   - Accessor: getTotalAttribute() → sum of item subtotals
   - Accessor: getItemCountAttribute() → sum of quantities

2. CartItem (app/Models/CartItem.php)
   - fillable: cart_id, product_id, quantity
   - Relationships:
     belongsTo Cart
     belongsTo Product
   - Accessor: getSubtotalAttribute() → quantity * (sale_price ?? price)

3. Order (app/Models/Order.php)
   - Use SoftDeletes
   - keyType: string (uuid), incrementing: false
   - fillable: user_id, status, total_amount, shipping_address,
               payment_method, payment_status, note
   - casts:
     status → App\Enums\OrderStatus
     payment_status → App\Enums\PaymentStatus
     shipping_address → encrypted:array ← encrypted JSON
     total_amount → decimal:2
     deleted_at → datetime
   - Relationships:
     belongsTo User (nullable)
     hasMany OrderItem (items)

4. OrderItem (app/Models/OrderItem.php)
   - fillable: order_id, product_id, product_name, product_sku, quantity, unit_price
   - casts: unit_price → decimal:2
   - Relationships:
     belongsTo Order
     belongsTo Product (nullable)
   - Accessor: getSubtotalAttribute() → quantity * unit_price

Output: 4 model files with correct relationships and casts
Test: php artisan tinker → Order::query()->toSql() returns valid SQL
```

---

## S14 — Models: Blog

```
Create Eloquent models for the Blog group.

Models to create:
1. BlogCategory (app/Models/BlogCategory.php)
   - keyType: int, incrementing: true
   - fillable: parent_id, name, slug, description, is_active
   - casts: is_active → boolean
   - Relationships:
     belongsTo BlogCategory (parent)
     hasMany BlogCategory (children)
     hasMany BlogPost (posts)
   - Scope: scopeActive($query)

2. BlogPost (app/Models/BlogPost.php)
   - Use SoftDeletes, Searchable (Scout)
   - keyType: string (uuid), incrementing: false
   - fillable: author_id, blog_category_id, title, slug, excerpt,
               content, featured_image, status, published_at
   - casts:
     status → App\Enums\BlogPostStatus
     published_at → datetime
     deleted_at → datetime
   - Relationships:
     belongsTo User (author) via author_id
     belongsTo BlogCategory
     belongsToMany BlogTag (tags) via blog_post_tag
     hasMany BlogComment (comments)
   - Scope: scopePublished($query) → status=published, published_at <= now
   - toSearchableArray(): title, excerpt, author name, category name, status

3. BlogTag (app/Models/BlogTag.php)
   - keyType: int, incrementing: true
   - fillable: name, slug
   - Relationships:
     belongsToMany BlogPost (posts) via blog_post_tag

4. BlogComment (app/Models/BlogComment.php)
   - keyType: int, incrementing: true
   - fillable: blog_post_id, user_id, body, is_approved
   - casts: is_approved → boolean
   - Relationships:
     belongsTo BlogPost
     belongsTo User (nullable)
   - Scope: scopeApproved($query)

Output: 4 model files, Scout searchable on BlogPost
```

---

## S15 — Models: SEO & GEO

```
Create Eloquent models for the SEO & GEO group.
All polymorphic models use string(36) model_id — no uuid type.

Reference: ERD.md section 3.5

Models to create in app/Models/Seo/:
1. SeoMeta
   - fillable: model_type, model_id, meta_title, meta_description, meta_keywords,
               og_title, og_description, og_image, og_type, twitter_card,
               twitter_title, twitter_description, canonical_url, robots
   - casts: og_type → App\Enums\OgType

2. GeoEntityProfile
   - fillable: model_type, model_id, ai_summary, key_facts, faq,
               use_cases, target_audience, llm_context_hint
   - casts: key_facts → array, faq → array

3. JsonldTemplate
   - fillable: schema_type, label, template, placeholders, is_auto_generated
   - casts: template → array, placeholders → array, is_auto_generated → boolean
   - schema_type cast → App\Enums\JsonldSchemaType

4. JsonldSchema
   - fillable: model_type, model_id, schema_type, label, payload,
               is_active, is_auto_generated, sort_order
   - casts: payload → array, is_active → boolean, is_auto_generated → boolean
   - schema_type cast → App\Enums\JsonldSchemaType

5. LlmsDocument
   - fillable: name, slug, title, description, scope, model_type,
               entry_count, last_generated_at, is_active
   - casts: scope → App\Enums\LlmsScope, last_generated_at → datetime

6. LlmsEntry
   - fillable: llms_document_id, model_type, model_id, title, url,
               summary, key_facts_text, faq_text, is_active
   - Relationship: belongsTo LlmsDocument

7. Redirect (app/Models/Seo/Redirect.php)
   - fillable: from_path, to_path, type, hits, cache_version, is_active
   - casts: type → App\Enums\RedirectType, is_active → boolean

8. SitemapIndex
   - fillable: name, filename, url, entry_count, last_generated_at, is_active
   - casts: last_generated_at → datetime
   - Relationship: hasMany SitemapEntry

9. SitemapEntry
   - fillable: sitemap_index_id, model_type, model_id, url,
               changefreq, priority, last_modified, is_active
   - casts: changefreq → App\Enums\SitemapChangefreq, last_modified → datetime
   - Relationship: belongsTo SitemapIndex

Output: 9 model files in app/Models/Seo/
```

---

## S16 — Models: Shared + Media + ActivityLog

```
Create the final shared infrastructure models.

Models to create:
1. Media (app/Models/Media.php)
   - fillable: model_type, model_id, collection, path, disk, mime_type, size
   - casts: size → integer
   - Accessor: getUrlAttribute() → Storage::disk($this->disk)->url($this->path)

2. ActivityLog (app/Models/ActivityLog.php)
   - fillable: log_name, description, subject_type, subject_id,
               causer_type, causer_id, properties
   - casts: properties → array
   - Relationships:
     morphTo subject
     morphTo causer

Output: 2 model files

Test: php artisan tinker → all models load without errors:
[User, Address, Category, Product, ProductImage, ProductVideo,
Cart, CartItem, Order, OrderItem, BlogCategory, BlogPost, BlogTag, BlogComment,
SeoMeta, GeoEntityProfile, JsonldTemplate, JsonldSchema, LlmsDocument, LlmsEntry,
Redirect, SitemapIndex, SitemapEntry, Media, ActivityLog]
→ each::query()->toSql() returns valid SQL
```

---

## S17 — SEO Traits

```
Create the SEO/GEO capability traits in app/Traits/.

Reference: FOLDER_STRUCTURE.md app/Traits/, CLAUDE.md SEO rules

Traits to create:
1. HasSeoMeta (app/Traits/HasSeoMeta.php)
   - morphOne SeoMeta using ('model_type', 'model_id')
   - public function seoMeta(): MorphOne
   - Helper: getSeoTitle() → seoMeta->meta_title ?? $this->name ?? $this->title

2. HasGeoProfile (app/Traits/HasGeoProfile.php)
   - morphOne GeoEntityProfile using ('model_type', 'model_id')
   - public function geoProfile(): MorphOne

3. HasJsonldSchemas (app/Traits/HasJsonldSchemas.php)
   - morphMany JsonldSchema using ('model_type', 'model_id')
   - public function jsonldSchemas(): MorphMany
   - Scope: activeSchemas() → is_active=true ordered by sort_order

4. HasSitemapEntry (app/Traits/HasSitemapEntry.php)
   - morphOne SitemapEntry using ('model_type', 'model_id')
   - public function sitemapEntry(): MorphOne

5. HasLlmsEntry (app/Traits/HasLlmsEntry.php)
   - morphMany LlmsEntry using ('model_type', 'model_id')
   - public function llmsEntries(): MorphMany

6. HasMedia (app/Traits/HasMedia.php)
   - morphMany Media using ('model_type', 'model_id')
   - public function media(): MorphMany
   - Helper: getFirstMedia(string $collection = 'default'): ?Media
   - Helper: getFirstMediaUrl(string $collection = 'default'): ?string

7. HasActivityLog (app/Traits/HasActivityLog.php)
   - morphMany ActivityLog via (subject_type, subject_id)
   - public function activityLogs(): MorphMany

Now apply traits to models:
- Product → use HasSeoMeta, HasGeoProfile, HasJsonldSchemas, HasSitemapEntry, HasLlmsEntry, HasMedia
- Category → use HasSeoMeta, HasGeoProfile, HasJsonldSchemas, HasSitemapEntry, HasLlmsEntry, HasMedia
- BlogPost → use HasSeoMeta, HasGeoProfile, HasJsonldSchemas, HasSitemapEntry, HasLlmsEntry, HasMedia
- BlogCategory → use HasSeoMeta

Output: 7 trait files, applied to correct models
Test: php artisan tinker → (new Product)->seoMeta() returns MorphOne instance
```

---

## S18 — AppServiceProvider + morphMap

```
Configure the AppServiceProvider with morphMap and other boot-time registrations.

Reference: CLAUDE.md morphMap section, ERD.md section 5

Tasks in app/Providers/AppServiceProvider.php:
1. Register morphMap in boot():
   Relation::morphMap([
     'product'       => \App\Models\Product::class,
     'blog_post'     => \App\Models\BlogPost::class,
     'category'      => \App\Models\Category::class,
     'blog_category' => \App\Models\BlogCategory::class,
     'blog_tag'      => \App\Models\BlogTag::class,
   ]);

2. Register Observers in boot() (stubs only — actual observer classes created in S33-S34):
   Product::observe(\App\Observers\ProductObserver::class);
   Category::observe(\App\Observers\CategoryObserver::class);
   BlogPost::observe(\App\Observers\BlogPostObserver::class);
   Redirect::observe(\App\Observers\RedirectObserver::class);
   Cart::observe(\App\Observers\CartObserver::class);

3. Create ObserverServiceProvider (app/Providers/ObserverServiceProvider.php):
   - Registers all observers (alternative approach if preferred)

4. Create stub Observer classes (empty for now — will be filled in S33-S34):
   - app/Observers/ProductObserver.php (saved, deleted stubs)
   - app/Observers/CategoryObserver.php
   - app/Observers/BlogPostObserver.php
   - app/Observers/RedirectObserver.php
   - app/Observers/CartObserver.php

5. Register config/seo.php:
   Return [
     'app_name' => env('APP_NAME', 'YourShop'),
     'app_url' => env('APP_URL', 'http://localhost'),
     'default_og_image' => env('APP_URL').'/og-default.jpg',
     'twitter_handle' => '',
   ]

Output: morphMap registered, observer stubs created
Test: php artisan tinker → Relation::morphMap() returns correct map
```

---

## S19 — Auth: Sanctum + Google OAuth Setup

```
Install and configure auth packages.

Packages to install:
- composer require laravel/sanctum
- composer require laravel/socialite
- composer require spatie/laravel-permission
- composer require spatie/laravel-activitylog
- php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
- php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

Configuration:
1. config/sanctum.php:
   - stateful_domains: [env('FRONTEND_URL', 'localhost:3000')]

2. config/cors.php:
   - allowed_origins: [env('FRONTEND_URL', 'http://localhost:3000')]
   - allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
   - allowed_headers: ['Content-Type', 'Authorization', 'X-Session-ID', 'Accept']
   - supports_credentials: true

3. config/services.php — add Google:
   'google' => [
     'client_id' => env('GOOGLE_CLIENT_ID'),
     'client_secret' => env('GOOGLE_CLIENT_SECRET'),
     'redirect' => env('GOOGLE_REDIRECT_URI'),
   ]

4. Add Sanctum middleware to api group in bootstrap/app.php

5. Create ApiResponse trait (app/Http/Resources/Traits/ApiResponse.php):
   - success(mixed $data, string $message, array $meta): JsonResponse
   - error(string $message, int $code, array $errors): JsonResponse
   - paginationMeta(LengthAwarePaginator $paginator): array

6. Create ForceJsonResponse middleware (app/Http/Middleware/ForceJsonResponse.php):
   - Sets Accept: application/json on all API requests

Output: Sanctum + Socialite + Spatie configured, ApiResponse trait ready
Test: php artisan config:clear && php artisan route:list shows sanctum routes
```

---

## S20 — Auth: Register + Login API

```
Build the Register and Login API endpoints.

Files to create:
1. app/Http/Requests/Auth/RegisterRequest.php
   - rules: name required string max:255
             email required email unique:users
             password required string min:8 confirmed

2. app/Http/Requests/Auth/LoginRequest.php
   - rules: email required email
             password required string

3. app/Services/Auth/AuthService.php
   - register(array $data): array → creates user, assigns customer role, returns token + user
   - login(array $credentials): array → validates credentials, returns token + user
   - logout(User $user): void → delete current access token

4. app/Http/Resources/Api/UserResource.php
   - Returns: id, name, email, phone, role, email_verified_at, created_at

5. app/Http/Controllers/Api/V1/Auth/AuthController.php
   - register(RegisterRequest): calls AuthService::register, returns 201
   - login(LoginRequest): calls AuthService::login, returns 200
   - logout(Request): calls AuthService::logout, returns 200
   - me(Request): returns UserResource of auth user

6. routes/api.php — add:
   Route::prefix('v1')->group(function () {
     Route::prefix('auth')->group(function () {
       Route::post('register', [AuthController::class, 'register']);
       Route::post('login', [AuthController::class, 'login']);
       Route::middleware('auth:sanctum')->group(function () {
         Route::post('logout', [AuthController::class, 'logout']);
         Route::get('me', [AuthController::class, 'me']);
       });
     });
   });

Test with:
POST /api/v1/auth/register → 201 with token
POST /api/v1/auth/login → 200 with token
GET /api/v1/auth/me with Bearer token → 200 with user

Output: Register + Login + Me + Logout endpoints working
```

---

## S21 — Auth: Google OAuth API

```
Build the Google OAuth endpoint.

Files to create:
1. app/Services/Auth/SocialAuthService.php
   - handleGoogle(string $idToken): array
     → Verify token via Socialite::driver('google')->userFromToken($idToken)
     → Find or create user by google_id or email
     → Assign customer role if new user
     → Return token + user + is_new_user flag

2. app/Http/Requests/Auth/GoogleAuthRequest.php
   - rules: id_token required string

3. app/Http/Controllers/Api/V1/Auth/SocialAuthController.php
   - google(GoogleAuthRequest): calls SocialAuthService::handleGoogle

4. routes/api.php — add inside v1/auth group:
   Route::post('google', [SocialAuthController::class, 'google']);

Test with a valid Google ID token:
POST /api/v1/auth/google → 200 with token, user, is_new_user

Output: Google OAuth endpoint working, user created/found correctly
```

---

## S22 — Auth: Update Profile API

```
Build the Update Profile endpoint.

Files to create:
1. app/Http/Requests/Auth/UpdateProfileRequest.php
   - rules: name sometimes string max:255
             phone sometimes string max:20 nullable

2. Update AuthService with:
   - updateProfile(User $user, array $data): User

3. Add to AuthController:
   - update(UpdateProfileRequest): calls AuthService::updateProfile, returns UserResource

4. routes/api.php — add inside auth sanctum group:
   Route::put('me', [AuthController::class, 'update']);

Test:
PUT /api/v1/auth/me with Bearer token → 200 with updated user

Output: Full auth suite complete (register, login, google, me, update, logout)
Test: php artisan test --filter=AuthTest → all pass
```

---

## S23 — Filament: Install + Admin Auth

```
Install and configure Filament v3 admin panel.

Steps:
1. composer require filament/filament:"^3.0"
2. php artisan filament:install --panels
   - Panel ID: admin
   - Path: /admin

3. Configure AdminPanelProvider (app/Providers/Filament/AdminPanelProvider.php):
   - path: 'admin'
   - login page: default Filament login
   - brandName: env('APP_NAME')
   - colors: primary → use a blue palette
   - Add all resources (empty for now — filled in S24-S32)

4. Restrict admin access to role=admin only:
   Add canAccess() to User model:
   public function canAccessPanel(Panel $panel): bool {
     return $this->role === UserRole::Admin;
   }

5. Create admin user if not exists:
   php artisan make:filament-user
   (Or verify AdminUserSeeder already created admin@example.com / password)

6. Configure navigation groups in AdminPanelProvider:
   Groups: Catalog, Commerce, Blog, SEO & GEO, System

Test:
- docker compose up -d
- Visit http://localhost/admin
- Login with admin@example.com / password
- Dashboard loads without errors

Output: Filament admin panel accessible, admin login works
```

---

## S24 — Filament: Category Resource

```
Create the Filament Category resource with full CRUD.

File: app/Filament/Resources/CategoryResource.php

Form fields:
- parent_id: Select (options from categories, nullable, searchable)
- name: TextInput (required, live → auto-fills slug)
- slug: TextInput (required, unique)
- description: Textarea (nullable)
- image_path: FileUpload (disk: public, directory: categories, nullable)
- sort_order: TextInput (numeric, default 0)
- is_active: Toggle (default true)

Table columns:
- name (searchable, sortable)
- parent.name (label: Parent)
- sort_order (sortable)
- is_active (badge: green/red)
- products_count (label: Products)
- created_at (sortable)

Table filters:
- is_active (ternary)
- parent_id (select)

Table actions:
- Edit, Delete (soft delete)
- Bulk: Delete

Navigation:
- Group: Catalog
- Icon: heroicon-o-tag
- Badge: Category::count()

Output: Category CRUD working in admin panel
Test: Create a category, edit it, verify soft delete works
```

---

## S25 — Filament: Product Resource

```
Create the Filament Product resource with full CRUD.

File: app/Filament/Resources/ProductResource.php

Form fields (use Tabs for organization):
Tab 1 — General:
- category_id: Select (searchable, required)
- name: TextInput (required, live → auto-fills slug)
- slug: TextInput (required, unique)
- sku: TextInput (required, unique)
- short_description: Textarea
- is_active: Toggle

Tab 2 — Pricing & Stock:
- price: TextInput (numeric, prefix: ₫, required)
- sale_price: TextInput (numeric, prefix: ₫, nullable)
- stock_quantity: TextInput (numeric, required)

Tab 3 — Description:
- description: RichEditor (or Textarea for now — TinyMCE configured in S25b)

Tab 4 — Images:
- Repeater for product_images:
  FileUpload (disk: public, directory: products/{year}/{month})
  TextInput for alt_text
  TextInput for sort_order

Tab 5 — Videos:
- Repeater for product_videos:
  FileUpload (disk: public, directory: products/{year}/{month})
  FileUpload for thumbnail_path

Table columns:
- thumbnail (first image, ImageColumn)
- name (searchable, sortable)
- sku (searchable)
- category.name
- price (money format)
- sale_price
- stock_quantity (sortable)
- is_active (badge)
- created_at (sortable, default sort desc)

Table filters: is_active, category_id

Navigation:
- Group: Catalog
- Icon: heroicon-o-cube

Output: Product CRUD working with image uploads
Test: Create a product with image, verify stored in storage/app/public/products/
```

---

## S26 — Filament: Blog Resource

```
Create Filament resources for Blog (posts, categories, tags, comments).

Files:
1. app/Filament/Resources/BlogPostResource.php
   Form fields (Tabs):
   Tab 1 — Content:
   - blog_category_id: Select (searchable)
   - title: TextInput (required, live → slug)
   - slug: TextInput (required, unique)
   - excerpt: Textarea
   - content: RichEditor
   - featured_image: FileUpload (disk: public, directory: blog/{year}/{month})
   - tags: Select (multiple, relationship, createOptionForm)

   Tab 2 — Publishing:
   - status: Select (enum options, required)
   - published_at: DateTimePicker (nullable)
   - author_id: Select (users with admin role)

   Table columns: title, status (badge), blog_category, author, published_at, created_at
   Filters: status, blog_category_id

2. app/Filament/Resources/BlogCategoryResource.php
   - Similar to CategoryResource but for blog_categories table
   - Fields: parent_id, name, slug, description, is_active

3. app/Filament/Resources/BlogTagResource.php
   - Fields: name, slug
   - Table: name, slug, posts_count

4. app/Filament/Resources/BlogCommentResource.php
   - Fields: body (readonly), is_approved (Toggle)
   - Table: blog_post.title, user.name, body (truncated), is_approved, created_at
   - Filter: is_approved
   - Bulk action: Approve selected

Navigation Group: Blog

Output: All blog resources working in admin panel
Test: Create a blog post with tags, approve a comment
```

---

## S27 — Filament: Order Resource

```
Create the Filament Order resource (view + status management, no create).

File: app/Filament/Resources/OrderResource.php

Table columns:
- id (truncated UUID, copyable)
- user.name (searchable)
- status (badge with colors: pending=warning, processing=info, shipped=primary, delivered=success, cancelled=danger)
- payment_status (badge)
- total_amount (money format ₫)
- items_count
- created_at (sortable, default desc)

Filters:
- status (select)
- payment_status (select)
- created_at (date range)

View page (no create, no edit form — use Actions instead):
- Display order detail: shipping_address, note, items table
- Action: Update Status (select new status, confirm modal)
- Action: Mark as Paid

Infolist sections:
- Customer info: name, email, phone
- Shipping address: full breakdown
- Items: table with product_name, sku, quantity, unit_price, subtotal
- Order totals: total_amount, payment_status

Navigation:
- Group: Commerce
- Icon: heroicon-o-shopping-bag
- Badge: Order::where('status', 'pending')->count()

Output: Orders visible in admin, status can be updated
Test: View an order, update status from pending to processing
```

---

## S28 — Filament: SEO Meta Resource

```
Create the Filament SEO Meta resource for managing meta tags per entity.

File: app/Filament/Resources/SeoMetaResource.php

Table columns:
- model_type (badge)
- model_id
- meta_title
- robots
- updated_at

Form fields (Tabs):
Tab 1 — Basic SEO:
- model_type: TextInput (readonly)
- model_id: TextInput (readonly)
- meta_title: TextInput (maxlength 160, char counter)
- meta_description: Textarea (maxlength 320, char counter)
- meta_keywords: TextInput
- canonical_url: TextInput
- robots: Select (options: index follow, noindex nofollow, noindex follow)

Tab 2 — Open Graph:
- og_title: TextInput (maxlength 160)
- og_description: Textarea
- og_image: TextInput (URL)
- og_type: Select (website, article, product)

Tab 3 — Twitter:
- twitter_card: Select
- twitter_title: TextInput
- twitter_description: Textarea

Navigation:
- Group: SEO & GEO
- Icon: heroicon-o-magnifying-glass

Output: SEO Meta resource working in admin panel
Test: Edit seo_meta for a product, verify saved correctly
```

---

## S29 — Filament: GEO Profile Resource

```
Create the Filament GEO Entity Profile resource.

File: app/Filament/Resources/GeoEntityProfileResource.php

Form fields:
- model_type: TextInput (readonly)
- model_id: TextInput (readonly)
- ai_summary: Textarea (rows: 4, helper: "2-3 sentences, plain text, no marketing language")
- target_audience: TextInput
- use_cases: Textarea
- llm_context_hint: Textarea (helper: "Extra context to help AI understand this entity correctly")
- key_facts: KeyValue (repeater with label/value pairs)
- faq: Repeater with:
    - question: TextInput
    - answer: Textarea

Table columns:
- model_type (badge)
- model_id
- has_summary (boolean icon)
- has_key_facts (boolean icon: count of facts)
- has_faq (boolean icon: count of Q&A)
- updated_at

Navigation:
- Group: SEO & GEO
- Icon: heroicon-o-cpu-chip

Output: GEO Profile resource working
Test: Add ai_summary and key_facts to a product profile
```

---

## S30 — Filament: JSON-LD Resource

```
Create the Filament JSON-LD resource for managing structured data.

Files:
1. app/Filament/Resources/JsonldTemplateResource.php
   Form fields:
   - schema_type: Select (enum JsonldSchemaType options, unique)
   - label: TextInput
   - is_auto_generated: Toggle
   - template: Textarea (JSON, rows: 20)
   - placeholders: KeyValue

   Table columns: schema_type, label, is_auto_generated badge, updated_at

2. app/Filament/Resources/JsonldSchemaResource.php
   Form fields:
   - model_type: TextInput (readonly)
   - model_id: TextInput (readonly)
   - schema_type: Select (enum options)
   - label: TextInput
   - is_active: Toggle
   - is_auto_generated: Toggle (if false = manual override)
   - sort_order: TextInput (numeric)
   - payload: Textarea (JSON, rows: 25)

   Table columns: model_type, schema_type, label, is_active badge, is_auto_generated badge, updated_at

Navigation:
- Group: SEO & GEO
- Icon: heroicon-o-code-bracket

Output: JSON-LD template and schema management working
Test: View seeded Product JSON-LD template, verify placeholder structure
```

---

## S31 — Filament: Redirects Resource

```
Create the Filament Redirects resource.

File: app/Filament/Resources/RedirectResource.php

Form fields:
- from_path: TextInput (required, placeholder: /old-slug, unique)
- to_path: TextInput (required, placeholder: /products/new-slug)
- type: Select (301 Permanent, 302 Temporary)
- is_active: Toggle

Table columns:
- from_path (searchable, copyable)
- to_path
- type (badge: 301=warning, 302=info)
- hits (sortable)
- is_active (badge)
- updated_at

Header actions:
- Create redirect button

Table actions:
- Edit, Delete
- Bulk: Delete, Toggle active

Navigation:
- Group: SEO & GEO
- Icon: heroicon-o-arrows-right-left

Note: After create/update/delete, the RedirectObserver (S34) will
increment cache_version to bust Redis cache automatically.

Output: Redirect management working in admin panel
Test: Create a redirect /test → /products, verify row saved
```

---

## S32 — Filament: Sitemap + LLMs Resources

```
Create Filament resources for Sitemap and LLMs management.

Files:
1. app/Filament/Resources/SitemapIndexResource.php
   Table columns: name, filename, entry_count, last_generated_at, is_active badge
   Actions: Regenerate (calls SitemapService), Toggle active
   No create/edit form — seeded data only, managed programmatically

2. app/Filament/Resources/LlmsDocumentResource.php
   Table columns: name, slug, scope badge, model_type, entry_count, last_generated_at, is_active
   Actions: Regenerate (calls LlmsGeneratorService), Toggle active
   No create/edit — seeded data only

3. ActivityLog viewer (read-only):
   app/Filament/Resources/ActivityLogResource.php
   Table only (no create/edit/delete):
   Columns: log_name, description, subject_type, causer (user name), created_at
   Filters: log_name, created_at range
   No actions except view

Navigation:
- Sitemap + LLMs → Group: SEO & GEO
- ActivityLog → Group: System
- Icon: Sitemap = heroicon-o-map, LLMs = heroicon-o-document-text

Output: All 3 resources visible in admin, activity log shows seeder actions
Note: Actual regeneration logic added in S36-S38
```

---

## S33 — Observers: Product + Category

```
Implement the ProductObserver and CategoryObserver.

Reference: CLAUDE.md Observers section, FOLDER_STRUCTURE.md

1. app/Observers/ProductObserver.php
   saved(Product $product):
   - dispatch(new SyncJsonldSchema($product))->onQueue('seo')
   - dispatch(new SyncSitemapEntry($product))->onQueue('seo')
   - dispatch(new SyncLlmsEntry($product))->onQueue('seo')

   deleted(Product $product):
   - Update sitemap_entries: set is_active=false where model matches
   - Update llms_entries: set is_active=false where model matches

2. app/Observers/CategoryObserver.php
   saved(Category $category):
   - dispatch(new SyncSitemapEntry($category))->onQueue('seo')
   - dispatch(new SyncLlmsEntry($category))->onQueue('seo')
   (No JSON-LD for categories at launch — only sitemap + llms)

   deleted(Category $category):
   - Same deactivation as ProductObserver

3. app/Observers/CartObserver.php
   creating(Cart $cart):
   - If user_id not null: set expires_at = now()->addDays(30)
   - If guest: set expires_at = now()->addDays(7)

   updated(Cart $cart):
   - Extend expires_at on every cart update (reset timer)

Note: Job classes are stubs now — implemented in S35

Output: Observers registered, fire correctly on model save
Test: php artisan tinker → (new Product([...]))->save() → check dispatch called
```

---

## S34 — Observers: BlogPost + Redirect

```
Implement BlogPostObserver and RedirectObserver.

1. app/Observers/BlogPostObserver.php
   saved(BlogPost $blogPost):
   - Only trigger SEO sync if status === BlogPostStatus::Published
   - dispatch(new SyncJsonldSchema($blogPost))->onQueue('seo')
   - dispatch(new SyncSitemapEntry($blogPost))->onQueue('seo')
   - dispatch(new SyncLlmsEntry($blogPost))->onQueue('seo')

   deleted(BlogPost $blogPost):
   - Deactivate sitemap_entries and llms_entries

2. app/Observers/RedirectObserver.php
   saved(Redirect $redirect):
   - Increment cache_version: $redirect->increment('cache_version')
   - Flush Redis key: Cache::forget('redirects:v*') or tag-based flush
   - Rebuild redirect cache: RedirectCacheService::rebuild() (stub)

   deleted(Redirect $redirect):
   - Same cache invalidation as saved

Output: All 5 observers implemented and registered
Test: Update a BlogPost status to 'published' → verify SEO jobs dispatched
     Create a Redirect → verify cache_version incremented
```

---

## S35 — Jobs: SEO Sync Jobs

```
Create the queued SEO sync job classes.

Reference: FOLDER_STRUCTURE.md app/Jobs/Seo/

Jobs to create:
1. app/Jobs/Seo/SyncJsonldSchema.php
   - Constructor: accepts Model $model
   - handle(): 
     → Get JsonldTemplate for each applicable schema_type
     → Resolve {{placeholders}} from model attributes
     → Upsert JsonldSchema rows (is_auto_generated=true only)
     → Skip rows where is_auto_generated=false (manual overrides)
   - onQueue: 'seo'
   - Retries: 3, backoff: 5 seconds

2. app/Jobs/Seo/SyncSitemapEntry.php
   - Constructor: accepts Model $model
   - handle():
     → Determine sitemap_index_id from model_type
     → Build canonical URL from model slug
     → Upsert sitemap_entries row
     → Update sitemap_indexes.entry_count
   - onQueue: 'seo'
   - Retries: 3

3. app/Jobs/Seo/SyncLlmsEntry.php
   - Constructor: accepts Model $model
   - handle():
     → Load geoProfile relation
     → Flatten key_facts jsonb → key_facts_text plain string
     → Flatten faq jsonb → faq_text plain string
     → Upsert llms_entries row in correct llms_document
     → Update llms_documents.entry_count
   - onQueue: 'seo'
   - Retries: 3

Helper for placeholder resolution in SyncJsonldSchema:
private function resolvePlaceholders(array $template, Model $model): array
  → Walk template array recursively
  → Replace {{model.field}} with actual model attribute value
  → Return resolved array

Output: 3 job classes created, properly queued
Test: php artisan tinker → dispatch(new SyncJsonldSchema(Product::first()))
     → Check jsonld_schemas table updated
```

---

## S36 — Services: JsonldService

```
Create the JsonldService for JSON-LD management.

File: app/Services/Seo/JsonldService.php

Methods:
1. syncForModel(Model $model): void
   - Loads applicable JsonldTemplates for this model type
   - Resolves placeholders
   - Upserts JsonldSchema rows
   - Skips is_auto_generated=false rows

2. getTemplateForType(JsonldSchemaType $type): ?JsonldTemplate
   - Cached in Redis for 60 min

3. resolvePlaceholders(array $template, Model $model): array
   - Recursive array walk
   - Handles: {{product.name}}, {{product.price}}, {{product.canonical_url}}
   - canonical_url = config('seo.app_url') . '/products/' . $model->slug
   - availability = stock_quantity > 0 ? 'InStock' : 'OutOfStock'

4. getActiveSchemas(Model $model): Collection
   - Returns jsonld_schemas where is_active=true ordered by sort_order
   - Used by Nuxt frontend via API response

5. buildBreadcrumbSchema(array $items): array
   - items: [['name' => '...', 'url' => '...']]
   - Returns BreadcrumbList JSON-LD array

Output: JsonldService with all methods
Test: php artisan tinker → app(JsonldService::class)->syncForModel(Product::first())
     → JsonldSchema::where('model_id', Product::first()->id)->count() > 0
```

---

## S37 — Services: SitemapService

```
Create the SitemapService for sitemap XML generation.

File: app/Services/Seo/SitemapService.php

Methods:
1. generateAll(): void
   - Calls generateChild() for each active SitemapIndex
   - Updates last_generated_at on each SitemapIndex

2. generateChild(SitemapIndex $index): void
   - Query active sitemap_entries for this index
   - Build XML string using PHP DOMDocument or simple string concat
   - Write to storage/app/public/sitemaps/{filename}
   - Update sitemap_indexes.entry_count + last_generated_at

3. generateIndex(): string
   - Build master sitemap.xml XML string from sitemap_indexes
   - Served dynamically — not written to disk

4. upsertEntry(Model $model, SitemapIndex $index): void
   - Find or create sitemap_entries row
   - Update url, last_modified, is_active

XML format:
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>{url}</loc>
    <lastmod>{last_modified Y-m-d}</lastmod>
    <changefreq>{changefreq}</changefreq>
    <priority>{priority}</priority>
  </url>
</urlset>

Output: SitemapService complete
Test: php artisan tinker → app(SitemapService::class)->generateAll()
     → Check storage/app/public/sitemaps/ folder has XML files
```

---

## S38 — Services: LlmsGeneratorService

```
Create the LlmsGeneratorService for llms.txt generation.

File: app/Services/Seo/LlmsGeneratorService.php

Methods:
1. generateAll(): void
   - Calls generateDocument() for each active LlmsDocument
   - Updates last_generated_at on each document

2. generateDocument(LlmsDocument $document): void
   - If scope = index: build index format (title + url + summary per entry)
   - If scope = full: build full format (title + url + summary + key_facts + faq)
   - Write to storage/app/public/llms/{slug}.txt
   - Update llms_documents.entry_count + last_generated_at

3. upsertEntry(Model $model, LlmsDocument $document): void
   - Load geoProfile for the model
   - Flatten key_facts: array → "  - Label: Value\n" per item
   - Flatten faq: array → "  Q: ...\n  A: ...\n" per item
   - Upsert llms_entries row

4. buildEntryBlock(LlmsEntry $entry): string
   Returns formatted text block:
   ## {title}
   URL: {url}
   Summary: {summary}
   Key Facts:
   {key_facts_text}
   FAQ:
   {faq_text}

5. buildIndexLine(LlmsEntry $entry): string
   Returns: - [{title}]({url}): {summary}

Output: LlmsGeneratorService complete
Test: php artisan tinker → app(LlmsGeneratorService::class)->generateAll()
     → Check storage/app/public/llms/ has .txt files
```

---

## S39 — Services: RedirectCacheService

```
Create the RedirectCacheService for Redis-based redirect resolution.

File: app/Services/Seo/RedirectCacheService.php

Methods:
1. getAll(): Collection
   - Get max cache_version from redirects table
   - Check Redis key: redirects:v{version}
   - If exists: return cached collection
   - If not: load active redirects from DB, cache with 60 min TTL, return

2. rebuild(): void
   - Load all active redirects from DB
   - Get current max cache_version
   - Store in Redis: redirects:v{version} with 3600 TTL
   - Old version keys expire naturally after 60 min

3. resolve(string $fromPath): ?Redirect
   - Call getAll(), find match by from_path
   - If found: increment hits in background job
   - Return Redirect or null

4. invalidate(): void
   - Called by RedirectObserver on any change
   - Calls rebuild() to refresh cache immediately

Redis key pattern: redirects:v{max_cache_version}
TTL: 3600 seconds (60 min fallback if rebuild fails)

Output: RedirectCacheService complete
Test: php artisan tinker:
   → Create a Redirect row
   → app(RedirectCacheService::class)->getAll() → returns collection
   → app(RedirectCacheService::class)->resolve('/test') → returns Redirect or null
```

---

## S40 — Artisan Commands: Sitemap + LLMs + CartPrune

```
Create the artisan commands for SEO generation and maintenance.

Commands to create:
1. app/Console/Commands/SitemapGenerateCommand.php
   Signature: sitemap:generate {--index= : specific index name to regenerate}
   Handle:
   - If --index option: generate only that child sitemap
   - Else: generate all via SitemapService::generateAll()
   - Output progress with $this->info()

2. app/Console/Commands/LlmsGenerateCommand.php
   Signature: llms:generate {--slug= : specific document slug to regenerate}
   Handle:
   - If --slug option: generate only that document
   - Else: generate all via LlmsGeneratorService::generateAll()
   - Output progress

3. app/Console/Commands/JsonldSyncCommand.php
   Signature: jsonld:sync {model : Model class short name e.g. Product} {--all : sync all records}
   Handle:
   - If --all: chunk through all records, dispatch SyncJsonldSchema per record
   - Else: sync single model by ID argument
   - Output: "Synced X records"

4. app/Console/Commands/CartPruneCommand.php
   Signature: cart:prune
   Handle:
   - Delete carts where expires_at < now()
   - Cascade deletes cart_items automatically
   - Output: "Pruned X expired carts"

Register in routes/console.php:
Schedule::command('sitemap:generate')->daily()->at('02:00');
Schedule::command('llms:generate')->daily()->at('02:30');
Schedule::command('cart:prune')->daily()->at('03:00');

Test:
php artisan sitemap:generate → no errors
php artisan llms:generate → no errors
php artisan cart:prune → "Pruned 0 expired carts"

Output: All 4 commands working
```

---

## S41 — Web Routes: Sitemap XML

```
Create the SitemapController and sitemap web routes.

File: app/Http/Controllers/Web/SitemapController.php

Methods:
1. index(): Response
   - Call SitemapService::generateIndex() for dynamic master sitemap
   - Return XML response with Content-Type: application/xml
   - Cache-Control: public, max-age=3600

2. child(string $name): Response
   - Find SitemapIndex by name
   - If not found or not active: abort(404)
   - Read file from storage/app/public/sitemaps/sitemap-{name}.xml
   - Return XML response
   - If file not found: call SitemapService::generateChild() on-the-fly

routes/web.php:
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{name}.xml', [SitemapController::class, 'child']);

Test:
GET /sitemap.xml → 200 application/xml with sitemap index structure
GET /sitemap-products.xml → 200 application/xml (may be empty if no products yet)

Output: Sitemap routes working
```

---

## S42 — Web Routes: LLMs TXT

```
Create the LlmsController and llms.txt web routes.

File: app/Http/Controllers/Web/LlmsController.php

Methods:
1. index(): Response
   - Find LlmsDocument where name='root' and scope='index'
   - Read from storage/app/public/llms/root.txt
   - If not found: generate on-the-fly via LlmsGeneratorService
   - Return plain text response with Content-Type: text/plain

2. full(): Response
   - Concatenate all active full-scope document files
   - Return plain text response

3. scoped(string $slug): Response
   - Find LlmsDocument by slug
   - If not found or not active: abort(404)
   - Read from storage/app/public/llms/{slug}.txt
   - If file not found: generate on-the-fly
   - Return plain text response

routes/web.php:
Route::get('llms.txt', [LlmsController::class, 'index']);
Route::get('llms-full.txt', [LlmsController::class, 'full']);
Route::get('llms-{slug}.txt', [LlmsController::class, 'scoped']);

Apply throttle: 30 requests per minute on these routes

Test:
GET /llms.txt → 200 text/plain
GET /llms-products.txt → 200 text/plain (empty if no products yet)
GET /llms-nonexistent.txt → 404

Output: LLMs routes working
```

---

## S43 — Web Routes: Health Check

```
Create the HealthController for system health monitoring.

File: app/Http/Controllers/Web/HealthController.php

Handle():
- Check PostgreSQL: DB::connection()->getPdo() !== null
- Check Redis: Redis::ping() === 'PONG'
- Check Meilisearch: Http::get(config('scout.meilisearch.host').'/health')->ok()
- Check Horizon: cache('horizon:status') → running/paused/inactive
- Check Storage: Storage::disk('public')->exists('.gitignore') or write test

Response 200 (all healthy):
{
  "status": "ok",
  "timestamp": "ISO8601",
  "services": {
    "database": "ok",
    "redis": "ok",
    "meilisearch": "ok",
    "horizon": "ok",
    "storage": "ok"
  }
}

Response 503 (any failure):
{
  "status": "degraded",
  "services": { ... with "error" for failed ones }
}

routes/web.php:
Route::get('health', HealthController::class);

Test:
GET /health → 200 with all services ok
Shut down Redis container → GET /health → 503 with redis: error

Output: Health endpoint working, returns correct HTTP codes
```

---

## S44 — Middleware: HandleRedirects

```
Create the HandleRedirects middleware.

File: app/Http/Middleware/HandleRedirects.php

Handle(Request $request, Closure $next):
1. Only process GET requests (skip POST, API calls)
2. Skip /api/* paths
3. Skip /admin/* paths
4. Get request path: $path = '/' . $request->path()
5. Call RedirectCacheService::resolve($path)
6. If redirect found and is_active:
   - Increment hits: dispatch a background job to avoid blocking
   - Return redirect($redirect->to_path, $redirect->type->value)
7. If no redirect: return $next($request)

Register in bootstrap/app.php:
- Add to web middleware group (before routing)
- Position: after StartSession, before RouteServiceProvider

Test:
1. Create redirect: /old-page → /products
2. GET /old-page → 301 redirect to /products
3. Check redirects.hits incremented to 1

Output: Redirect middleware working for web routes
```

---

## S45 — API: Category Endpoints

```
Create the Category API endpoints.

Files to create:
1. app/Http/Resources/Api/Category/CategoryResource.php
   Returns: id, name, slug, description, image_url, sort_order, is_active,
            parent (nested CategoryResource), children count, created_at

2. app/Http/Resources/Api/Category/CategoryTreeResource.php
   Returns: id, name, slug, image_url, sort_order,
            children (recursive CategoryTreeResource collection)

3. app/Services/Category/CategoryService.php
   - getTree(): Collection — all active categories nested with children
   - getBySlug(string $slug): Category — with products paginated
   - Cache tree in Redis for 10 min (bust on CategoryObserver saved)

4. app/Http/Controllers/Api/V1/Category/CategoryController.php
   - index(): return CategoryTreeResource (full nested tree)
   - show(string $slug): return CategoryResource + paginated products

5. routes/api.php — add inside v1 group:
   Route::get('categories', [CategoryController::class, 'index']);
   Route::get('categories/{slug}', [CategoryController::class, 'show']);

Test:
GET /api/v1/categories → 200 with nested tree
GET /api/v1/categories/led-panels → 200 with category + products
GET /api/v1/categories/nonexistent → 404

Output: Category endpoints working
```

---

## S46 — API: Product List + Detail

```
Create the Product API endpoints.

Files to create:
1. app/Http/Resources/Api/Product/ProductResource.php
   Returns: id, name, slug, sku, short_description, price, sale_price,
            stock_quantity, is_active, category (CategoryResource),
            thumbnail (first image url), created_at

2. app/Http/Resources/Api/Product/ProductDetailResource.php
   Extends ProductResource + adds:
   description, images[], videos[], seo{}, jsonld_schemas[]

3. app/Http/Resources/Api/Product/ProductCollection.php
   Wraps ProductResource with pagination meta

4. app/Services/Product/ProductService.php
   - list(array $filters, int $perPage): LengthAwarePaginator
     Filters: category (slug), sort, min_price, max_price, in_stock
   - getBySlug(string $slug): Product — with images, videos, seoMeta, jsonldSchemas

5. app/Http/Controllers/Api/V1/Product/ProductController.php
   - index(Request): return ProductCollection
   - show(string $slug): return ProductDetailResource

6. app/Http/Controllers/Api/V1/Product/ProductSearchController.php
   - __invoke(Request): Scout search on Product + BlogPost, return combined results

7. routes/api.php — add:
   Route::get('products', [ProductController::class, 'index']);
   Route::get('products/{slug}', [ProductController::class, 'show']);
   Route::get('search', ProductSearchController::class);

Test:
GET /api/v1/products → 200 paginated list
GET /api/v1/products?category=led-panels&sort=price_asc → filtered list
GET /api/v1/products/smart-led-panel → 200 with full detail + SEO
GET /api/v1/products/nonexistent → 404
GET /api/v1/search?q=LED → results from both products and blog

Output: Product endpoints working with filters and SEO data
```

---

## S47 — API: Search Endpoint

```
Configure Meilisearch and finalize the search endpoint.

Tasks:
1. Install Scout + Meilisearch driver:
   composer require laravel/scout
   composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle

2. config/scout.php:
   - driver: meilisearch
   - prefix: app_

3. Configure Product index settings (via MeilisearchIndex seeder or command):
   Searchable attributes: name, sku, short_description
   Filterable attributes: category_id, price, sale_price, is_active, stock_quantity
   Sortable attributes: price, created_at, name

4. Configure BlogPost index:
   Searchable: title, excerpt
   Filterable: status, blog_category_id
   Sortable: published_at

5. Import existing data (if any):
   php artisan scout:import "App\Models\Product"
   php artisan scout:import "App\Models\BlogPost"

6. Finalize ProductSearchController:
   - Validate: q (required string min:2), type (products|blog|all), page, per_page
   - Search products: Product::search($q)->where('is_active', true)->paginate($perPage)
   - Search blog: BlogPost::search($q)->where('status', 'published')->paginate($perPage)
   - Return combined results with separate keys

Test:
GET /api/v1/search?q=LED → products and blog results
GET /api/v1/search?q=LED&type=products → products only
GET /api/v1/search?q=x → 422 (too short, min 2 chars)

Output: Meilisearch connected, search endpoint returning results
```

---

## S48 — API: Cart

```
Create the Cart API endpoints (guest + auth).

Files to create:
1. app/Http/Resources/Api/Cart/CartResource.php
   Returns: id, expires_at, items (CartItemResource[]), total, item_count

2. app/Http/Resources/Api/Cart/CartItemResource.php
   Returns: id, product (ProductResource slim), quantity, subtotal

3. app/Http/Requests/Cart/AddCartItemRequest.php
   Rules: product_id uuid required exists:products,id, quantity integer min:1

4. app/Http/Requests/Cart/UpdateCartItemRequest.php
   Rules: quantity integer min:1 required

5. app/Services/Cart/CartService.php
   - resolveCart(Request $request): Cart
     → If auth: find/create cart by user_id, extend expires_at
     → If guest: find/create cart by X-Session-ID header, extend expires_at
   - addItem(Cart $cart, string $productId, int $quantity): CartItem
     → Check stock, upsert item, increment quantity if exists
   - updateItem(CartItem $item, int $quantity): CartItem
   - removeItem(CartItem $item): void
   - clearCart(Cart $cart): void
   - mergeGuestCart(User $user, string $sessionId): Cart
     → Find guest cart, move items to user cart, delete guest cart

6. app/Http/Controllers/Api/V1/Cart/CartController.php
   - show(Request): resolveCart → CartResource
   - clear(Request): clearCart
   - merge(Request): mergeGuestCart

7. app/Http/Controllers/Api/V1/Cart/CartItemController.php
   - store(AddCartItemRequest): addItem
   - update(UpdateCartItemRequest, CartItem): updateItem
   - destroy(CartItem): removeItem

8. routes/api.php:
   Route::get('cart', [CartController::class, 'show']);
   Route::delete('cart', [CartController::class, 'clear']);
   Route::post('cart/items', [CartItemController::class, 'store']);
   Route::put('cart/items/{cartItem}', [CartItemController::class, 'update']);
   Route::delete('cart/items/{cartItem}', [CartItemController::class, 'destroy']);
   Route::middleware('auth:sanctum')->post('cart/merge', [CartController::class, 'merge']);

Test:
GET /api/v1/cart (with X-Session-ID header) → empty cart created
POST /api/v1/cart/items → item added
PUT /api/v1/cart/items/1 → quantity updated
DELETE /api/v1/cart/items/1 → item removed

Output: Cart endpoints working for both guest and auth users
```

---

## S49 — API: Orders

```
Create the Order API endpoints.

Files to create:
1. app/Http/Resources/Api/Order/OrderResource.php
   Returns: id, status, payment_status, total_amount, shipping_address,
            note, items (OrderItemResource[]), created_at

2. app/Http/Resources/Api/Order/OrderItemResource.php
   Returns: product_name, product_sku, quantity, unit_price, subtotal

3. app/Http/Resources/Api/Order/OrderCollection.php

4. app/Http/Requests/Order/PlaceOrderRequest.php
   Rules: address_id uuid required exists:addresses,id, note string nullable max:500

5. app/Services/Order/OrderService.php
   - placeOrder(User $user, array $data): Order
     → Load cart and items (validate not empty)
     → Check stock for each item
     → Load address, snapshot as encrypted JSON
     → Create order + order_items (price snapshot)
     → Deduct stock_quantity per product
     → Clear cart
     → Dispatch SendOrderConfirmationEmail job (queue: orders)
   - cancel(Order $order): Order
     → Only if status = pending
     → Update status to cancelled
     → Restore stock_quantity (optional at launch)

6. app/Http/Controllers/Api/V1/Order/OrderController.php
   - index(Request): paginated orders for auth user
   - show(Request, Order): single order (policy: user owns order)
   - store(PlaceOrderRequest): placeOrder
   - cancel(Request, Order): cancel

7. app/Policies/OrderPolicy.php
   - view: order->user_id === auth user id

8. routes/api.php (inside auth:sanctum):
   Route::get('orders', [OrderController::class, 'index']);
   Route::post('orders', [OrderController::class, 'store']);
   Route::get('orders/{order}', [OrderController::class, 'show']);
   Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel']);

Test:
POST /api/v1/orders → 201 with order, cart cleared
GET /api/v1/orders → order history
PATCH /api/v1/orders/{id}/cancel → order cancelled
GET /api/v1/orders/{other-user-order-id} → 403

Output: Order endpoints working with stock management
```

---

## S50 — API: Addresses

```
Create the Address API endpoints.

Files to create:
1. app/Http/Resources/Api/AddressResource.php

2. app/Http/Requests/Address/StoreAddressRequest.php
   Rules: label enum, full_name required string, phone required string,
          address_line required string, city required, district required,
          ward required, is_default boolean

3. app/Http/Requests/Address/UpdateAddressRequest.php (same rules with sometimes)

4. app/Services/Address/AddressService.php
   - list(User $user): Collection
   - create(User $user, array $data): Address
     → If is_default=true: set all others to is_default=false first
   - update(Address $address, array $data): Address
   - delete(Address $address): void
   - setDefault(User $user, Address $address): Address

5. app/Http/Controllers/Api/V1/Address/AddressController.php
   - index, store, update, destroy, setDefault

6. app/Policies/AddressPolicy.php
   - All actions: address->user_id === auth user id

7. routes/api.php (inside auth:sanctum):
   Route::apiResource('addresses', AddressController::class);
   Route::patch('addresses/{address}/default', [AddressController::class, 'setDefault']);

Test:
POST /api/v1/addresses → created
PATCH /api/v1/addresses/{id}/default → is_default set, others cleared
DELETE /api/v1/addresses/{id} → deleted

Output: Address CRUD working with policy protection
```

---

## S51 — API: Blog Endpoints

```
Create the Blog API endpoints (list + detail + categories + tags).

Files to create:
1. app/Http/Resources/Api/Blog/BlogPostResource.php
   Returns: id, title, slug, excerpt, featured_image, status,
            author (name), category (BlogCategoryResource),
            tags (BlogTagResource[]), published_at

2. app/Http/Resources/Api/Blog/BlogPostDetailResource.php
   Extends BlogPostResource + adds: content, seo{}, jsonld_schemas[]

3. app/Http/Resources/Api/Blog/BlogCategoryResource.php
4. app/Http/Resources/Api/Blog/BlogTagResource.php

5. app/Services/Blog/BlogPostService.php
   - list(array $filters, int $perPage): LengthAwarePaginator
     Filters: category (slug), tag (slug), sort (newest/oldest)
   - getBySlug(string $slug): BlogPost — with all relations + SEO

6. app/Http/Controllers/Api/V1/Blog/BlogPostController.php
   - index(Request): paginated published posts
   - show(string $slug): single published post with full detail

7. app/Http/Controllers/Api/V1/Blog/BlogCategoryController.php
   - index(): full category tree

8. app/Http/Controllers/Api/V1/Blog/BlogTagController.php  (simple)
   - index(): all tags

9. routes/api.php:
   Route::get('blog', [BlogPostController::class, 'index']);
   Route::get('blog/categories', [BlogCategoryController::class, 'index']);
   Route::get('blog/tags', [BlogTagController::class, 'index']);
   Route::get('blog/{slug}', [BlogPostController::class, 'show']);

Test:
GET /api/v1/blog → paginated published posts only
GET /api/v1/blog/how-casambi-mesh-works → full post with SEO
GET /api/v1/blog/draft-post → 404 (drafts not exposed)

Output: Blog list and detail endpoints working
```

---

## S52 — API: Blog Comments

```
Create the Blog Comments API endpoints.

Files to create:
1. app/Http/Resources/Api/Blog/BlogCommentResource.php
   Returns: id, body, user (name only), created_at

2. app/Http/Requests/Blog/StoreBlogCommentRequest.php
   Rules: body required string min:3 max:2000

3. app/Http/Controllers/Api/V1/Blog/BlogCommentController.php
   - index(string $slug, Request): paginated approved comments
   - store(string $slug, StoreBlogCommentRequest): create comment
     → Find published BlogPost by slug
     → Create comment with is_approved=false
     → Return 201 with message "Comment pending approval"

4. routes/api.php:
   Route::get('blog/{slug}/comments', [BlogCommentController::class, 'index']);
   Route::middleware('auth:sanctum')
        ->post('blog/{slug}/comments', [BlogCommentController::class, 'store']);

Test:
GET /api/v1/blog/{slug}/comments → approved comments only
POST /api/v1/blog/{slug}/comments (auth) → 201, is_approved=false
POST /api/v1/blog/{slug}/comments (no auth) → 401

Output: Comment endpoints working, moderation flow correct
```

---

## S53 — Horizon + Queue Config

```
Install and configure Laravel Horizon for queue monitoring.

Steps:
1. composer require laravel/horizon
2. php artisan horizon:install
3. php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"

4. config/horizon.php:
   Configure queue workers per environment:
   environments:
     local:
       supervisor-1:
         connection: redis
         queue: [default, orders, seo, notifications]
         balance: auto
         minProcesses: 1
         maxProcesses: 4
         tries: 3
     production:
       supervisor-1:
         queue: [default]
         minProcesses: 1
         maxProcesses: 3
       supervisor-2:
         queue: [orders]
         minProcesses: 1
         maxProcesses: 5
       supervisor-3:
         queue: [seo]
         minProcesses: 1
         maxProcesses: 3

5. Restrict Horizon dashboard to admin only:
   In HorizonServiceProvider::gate():
   Gate::define('viewHorizon', function ($user) {
     return $user->role === UserRole::Admin;
   });

6. Verify Docker horizon service starts correctly:
   docker compose restart horizon
   Visit /horizon → dashboard accessible

Test:
- Dispatch a test job
- Visit /horizon → job appears in dashboard
- Job completes → moves to completed

Output: Horizon running, all 4 queues monitored
```

---

## S54 — Scheduler: Full Configuration

```
Finalize all scheduled tasks in routes/console.php.

All scheduled commands:
Schedule::command('sitemap:generate')->dailyAt('02:00')
  ->withoutOverlapping()
  ->runInBackground();

Schedule::command('llms:generate')->dailyAt('02:30')
  ->withoutOverlapping()
  ->runInBackground();

Schedule::command('cart:prune')->dailyAt('03:00')
  ->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('scout:import "App\Models\Product"')
  ->weekly()->sundays()->at('04:00');

Schedule::command('scout:import "App\Models\BlogPost"')
  ->weekly()->sundays()->at('04:30');

Verify Docker scheduler service runs:
docker compose logs scheduler
→ Should show "* * * * * php artisan schedule:run" output

Test: php artisan schedule:list → all commands listed with next run time

Output: All scheduled tasks configured and running via Docker
```

---

## S55 — Scribe API Documentation

```
Install and configure Scribe for auto-generated API documentation.

Steps:
1. composer require --dev knuckleswtf/scribe
2. php artisan vendor:publish --provider="Knuckleswtf\Scribe\ScribeServiceProvider" --tag=config

3. config/scribe.php:
   - type: laravel
   - title: 'YourShop API Documentation'
   - base_url: env('APP_URL')
   - auth.enabled: true
   - auth.default: true
   - auth.in: bearer
   - routes: include /api/v1/*
   - output_path: public/docs

4. Add PHPDoc blocks to key controllers:
   Example for ProductController::index():
   /**
    * @queryParam page int Page number. Default: 1.
    * @queryParam per_page int Items per page. Default: 20. Max: 100.
    * @queryParam category string Filter by category slug.
    * @response 200 scenario="Success" {"success":true,"data":[...],"meta":{...}}
    */

5. Generate docs:
   php artisan scribe:generate

6. Restrict /docs to non-production:
   In routes/web.php:
   if (app()->isLocal() || app()->environment('staging')) {
     Route::get('docs', fn() => view('scribe.index'));
   }

Test:
GET /docs → API documentation page loads
Postman collection exported to public/docs/collection.json

Output: API docs auto-generated, Postman collection available
```

---

## S56 — Feature Tests: Auth

```
Write feature tests for all Auth endpoints.

File: tests/Feature/Auth/AuthTest.php

Test cases:
1. test_user_can_register()
   POST /api/v1/auth/register with valid data
   → 201, response has token + user

2. test_register_fails_with_duplicate_email()
   POST /api/v1/auth/register with existing email
   → 422, errors.email not empty

3. test_register_fails_with_weak_password()
   POST /api/v1/auth/register with password < 8 chars
   → 422

4. test_user_can_login()
   POST /api/v1/auth/login with valid credentials
   → 200, response has token + user

5. test_login_fails_with_wrong_password()
   → 401 or 422

6. test_authenticated_user_can_get_profile()
   GET /api/v1/auth/me with Bearer token
   → 200, data.email matches

7. test_unauthenticated_user_cannot_get_profile()
   GET /api/v1/auth/me without token
   → 401

8. test_user_can_logout()
   POST /api/v1/auth/logout with Bearer token
   → 200, token deleted

9. test_user_can_update_profile()
   PUT /api/v1/auth/me with name change
   → 200, data.name updated

Run: php artisan test --filter=AuthTest
All tests must pass.

Output: Auth feature tests passing
```

---

## S57 — Feature Tests: Products + Categories

```
Write feature tests for Product and Category endpoints.

File: tests/Feature/Product/ProductTest.php

Test cases:
1. test_can_list_products() → 200, data is array, meta has pagination
2. test_can_filter_products_by_category() → filtered results
3. test_can_sort_products_by_price() → sorted correctly
4. test_can_get_product_detail() → 200, data has images, seo keys
5. test_product_detail_includes_jsonld_schemas() → data.jsonld_schemas not empty
6. test_nonexistent_product_returns_404() → 404
7. test_inactive_product_returns_404() → 404
8. test_can_search_products() → 200, data.products array

File: tests/Feature/Category/CategoryTest.php
1. test_can_get_category_tree() → 200, nested structure
2. test_can_get_category_with_products() → 200, has products
3. test_nonexistent_category_returns_404() → 404

Run: php artisan test --filter=ProductTest
Run: php artisan test --filter=CategoryTest
All must pass.

Output: Product and Category tests passing
```

---

## S58 — Feature Tests: Cart + Orders

```
Write feature tests for Cart and Order endpoints.

File: tests/Feature/Cart/CartTest.php
1. test_guest_can_get_empty_cart() (with X-Session-ID header) → 200
2. test_guest_can_add_item_to_cart() → 201, item in cart
3. test_cart_quantity_increments_on_duplicate_add() → quantity += 1
4. test_can_update_cart_item_quantity() → 200, updated
5. test_can_remove_cart_item() → 200, item gone
6. test_can_clear_cart() → 200, cart empty
7. test_auth_user_cart_persists_across_requests() → same cart returned

File: tests/Feature/Order/OrderTest.php
1. test_authenticated_user_can_place_order() → 201, cart cleared after
2. test_order_snapshots_product_price() → unit_price doesn't change when product price changes
3. test_order_deducts_stock() → product.stock_quantity decremented
4. test_user_can_cancel_pending_order() → status = cancelled
5. test_user_cannot_cancel_non_pending_order() → 422
6. test_user_cannot_view_another_users_order() → 403
7. test_unauthenticated_user_cannot_place_order() → 401

Run: php artisan test --filter=CartTest
Run: php artisan test --filter=OrderTest

Output: Cart and Order tests passing
```

---

## S59 — Feature Tests: Blog

```
Write feature tests for Blog endpoints.

File: tests/Feature/Blog/BlogTest.php
1. test_can_list_published_blog_posts() → only published posts
2. test_draft_posts_not_in_list() → drafts hidden
3. test_can_filter_blog_by_category() → filtered
4. test_can_filter_blog_by_tag() → filtered
5. test_can_get_blog_post_detail() → 200, has content, seo, jsonld_schemas
6. test_draft_post_detail_returns_404() → 404
7. test_can_list_approved_comments() → only approved
8. test_authenticated_user_can_submit_comment() → 201, is_approved=false
9. test_unauthenticated_user_cannot_comment() → 401
10. test_can_get_blog_categories() → 200 tree
11. test_can_get_blog_tags() → 200 list

Run: php artisan test --filter=BlogTest
All must pass.

Output: Blog tests passing
```

---

## S60 — Feature Tests: SEO Routes

```
Write feature tests for SEO and GEO web routes.

File: tests/Feature/Seo/SitemapTest.php
1. test_sitemap_index_returns_xml() → 200, Content-Type: application/xml
2. test_sitemap_index_contains_child_links() → has sitemap-products.xml reference
3. test_product_sitemap_returns_xml() → 200, application/xml
4. test_blog_sitemap_returns_xml() → 200
5. test_unknown_sitemap_returns_404() → 404

File: tests/Feature/Seo/LlmsTest.php
1. test_llms_txt_returns_plain_text() → 200, Content-Type: text/plain
2. test_llms_full_txt_returns_plain_text() → 200
3. test_llms_products_returns_plain_text() → 200
4. test_llms_unknown_slug_returns_404() → 404

File: tests/Feature/Seo/RedirectTest.php
1. test_active_redirect_returns_301() → 301 to correct URL
2. test_302_redirect_returns_302() → 302
3. test_inactive_redirect_not_followed() → 200 (page loads normally)
4. test_redirect_hits_incremented() → hits column incremented

File: tests/Feature/Seo/HealthTest.php
1. test_health_endpoint_returns_200() → 200, status: ok
2. test_health_endpoint_structure() → has services.database, redis, etc.

Run: php artisan test → ALL tests across all files pass

Output: All 60 sprints complete. Backend fully built and tested.
Summary: php artisan test → GREEN
```

---

## Final Verification Checklist

```
After S60 — run this full verification:

□ php artisan migrate:fresh --seed → no errors
□ php artisan test → all tests green
□ php artisan route:list → all routes listed
□ php artisan horizon → starts without errors
□ php artisan schedule:list → all tasks listed
□ php artisan scribe:generate → docs generated
□ php artisan sitemap:generate → XML files created
□ php artisan llms:generate → TXT files created
□ GET /health → all services ok
□ GET /sitemap.xml → valid XML
□ GET /llms.txt → valid plain text
□ GET /docs → API docs accessible
□ /admin login → Filament panel accessible
□ Create product in admin → Observer fires SEO jobs
□ Check Horizon dashboard → jobs completed
□ ./vendor/bin/pint → no formatting issues
□ ./vendor/bin/phpstan analyse → no errors (level 6)
```

---

*This file is the AI build plan for the backend. Each sprint is a self-contained, testable unit.
Feed one sprint at a time to Claude Code CLI for best quality and token efficiency.*