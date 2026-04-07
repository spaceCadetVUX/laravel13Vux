# ERD Documentation
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Database:** PostgreSQL
> **Version:** 2.0 — critical fixes applied
> **Last Updated:** April 2026

---

## Changelog v2.0
| Fix | Scope | Detail |
|---|---|---|
| `model_id` type | All 7 polymorphic tables | Changed `uuid` → `varchar(36)` to support both uuid and bigint PKs |
| Missing infra tables | Auth | Added `password_reset_tokens`, `personal_access_tokens`, `permission_tables` |
| Cart expiry | Commerce | Added `expires_at` + prune strategy to `carts` |
| Cache invalidation | SEO | Added `cache_version` + TTL strategy to `redirects` |
| Migration order | All | Renumbered to include new infra tables |

---

## Table of Contents
1. [Overview](#1-overview)
2. [Entity Groups](#2-entity-groups)
3. [Table Definitions](#3-table-definitions)
   - [Auth & Permissions](#31-auth--permissions)
   - [Product Catalog](#32-product-catalog)
   - [Cart & Orders](#33-cart--orders)
   - [Blog](#34-blog)
   - [SEO & GEO](#35-seo--geo)
   - [Shared Infrastructure](#36-shared-infrastructure)
4. [Relationships Summary](#4-relationships-summary)
5. [Key Design Decisions](#5-key-design-decisions)
6. [Migration Order](#6-migration-order)

---

## 1. Overview

```
E-commerce Side                     Blog Side
─────────────────────────           ──────────────────────────
users                               blog_posts
  └── addresses                       └── blog_comments
  └── carts (+ expires_at)            └── blog_post_tag (pivot)
        └── cart_items              blog_categories (nested)
  └── orders                        blog_tags
        └── order_items

categories (nested)
  └── products
        └── product_images
        └── product_videos

SEO & GEO Layer (polymorphic — varchar(36) model_id)
────────────────────────────────────────────────────
seo_meta            geo_entity_profiles     jsonld_templates
jsonld_schemas      llms_documents          llms_entries
redirects           sitemap_indexes         sitemap_entries

Shared
────────────────────────
media          (polymorphic — varchar(36) model_id)
activity_logs  (polymorphic — varchar(36) model_id)

Laravel Infrastructure (package-generated)
────────────────────────────────────────────
password_reset_tokens    personal_access_tokens
roles / permissions      model_has_roles / role_has_permissions
cache                    sessions
```

---

## 2. Entity Groups

| Group | Tables | Purpose |
|---|---|---|
| Auth & Permissions | `users`, `addresses`, `password_reset_tokens`, `personal_access_tokens`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Identity, auth, roles |
| Catalog | `categories`, `products`, `product_images`, `product_videos` | Product management |
| Commerce | `carts`, `cart_items`, `orders`, `order_items` | Shopping & checkout |
| Blog | `blog_posts`, `blog_categories`, `blog_tags`, `blog_post_tag`, `blog_comments` | Content management |
| SEO & GEO | `seo_meta`, `geo_entity_profiles`, `jsonld_schemas`, `jsonld_templates`, `llms_documents`, `llms_entries`, `redirects`, `sitemap_indexes`, `sitemap_entries` | Search & AI discoverability |
| Shared | `media`, `activity_logs`, `cache`, `sessions` | Polymorphic attachments, audit, fallback |

---

## 3. Table Definitions

### 3.1 Auth & Permissions

#### `users`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | Public-safe identifier |
| `name` | varchar(255) | NOT NULL | |
| `email` | text | NOT NULL, UNIQUE | Encrypted at rest |
| `phone` | text | nullable | Encrypted at rest |
| `password` | varchar(255) | nullable | null for Google-only accounts |
| `role` | enum | NOT NULL, default `customer` | `admin` \| `customer` |
| `google_id` | varchar(255) | nullable, UNIQUE | Socialite Google ID |
| `email_verified_at` | timestamp | nullable | |
| `remember_token` | varchar(100) | nullable | |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `email` (unique), `google_id` (unique), `role`, `deleted_at`

---

#### `addresses`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id, CASCADE | |
| `label` | enum | NOT NULL, default `home` | `home` \| `office` \| `other` |
| `full_name` | varchar(255) | NOT NULL | |
| `phone` | text | NOT NULL | Encrypted at rest |
| `address_line` | text | NOT NULL | Encrypted at rest |
| `city` | varchar(100) | NOT NULL | |
| `district` | varchar(100) | NOT NULL | |
| `ward` | varchar(100) | NOT NULL | |
| `is_default` | boolean | NOT NULL, default false | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `user_id`, `is_default`

---

#### `password_reset_tokens` *(Laravel built-in)*
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `email` | varchar(255) | PK | |
| `token` | varchar(255) | NOT NULL | Hashed |
| `created_at` | timestamp | nullable | |

> **Generated by:** `php artisan migrate` (included in Laravel core)

---

#### `personal_access_tokens` *(Sanctum)*
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `tokenable_type` | varchar(255) | NOT NULL | Polymorphic |
| `tokenable_id` | varchar(36) | NOT NULL | varchar(36) — consistent with polymorphic standard |
| `name` | varchar(255) | NOT NULL | Token name e.g. `api-token` |
| `token` | varchar(64) | NOT NULL, UNIQUE | SHA256 hash |
| `abilities` | text | nullable | JSON array of abilities |
| `last_used_at` | timestamp | nullable | |
| `expires_at` | timestamp | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Generated by:** `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
> **Note:** `tokenable_id` overridden to `varchar(36)` to match our polymorphic standard.

---

#### Spatie Permission Tables *(package-generated)*

**`roles`**
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(255) | `admin`, `customer` |
| `guard_name` | varchar(255) | `web` or `api` |
| `created_at` / `updated_at` | timestamp | |

**`permissions`**
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | e.g. `products.create`, `orders.view` |
| `name` | varchar(255) | |
| `guard_name` | varchar(255) | |
| `created_at` / `updated_at` | timestamp | |

**`model_has_roles`** — pivot: `role_id`, `model_type`, `model_id varchar(36)`
**`model_has_permissions`** — pivot: `permission_id`, `model_type`, `model_id varchar(36)`
**`role_has_permissions`** — pivot: `permission_id`, `role_id`

> **Generated by:** `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
> **Note:** Spatie's default `model_id` is `unsignedBigInteger`. Publish and edit the migration to change to `varchar(36)` before running.

---

### 3.2 Product Catalog

#### `categories`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `parent_id` | bigint | FK → categories.id, nullable | Self-referencing for nesting |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL, UNIQUE | |
| `description` | text | nullable | |
| `image_path` | varchar(500) | nullable | |
| `sort_order` | integer | NOT NULL, default 0 | |
| `is_active` | boolean | NOT NULL, default true | |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `slug` (unique), `parent_id`, `is_active`, `sort_order`
> **PK type note:** bigint — when attaching SEO/GEO/media to a category, the polymorphic `model_id` stores this as a string e.g. `"12"` in `varchar(36)`.

---

#### `products`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | Public-safe identifier |
| `category_id` | bigint | FK → categories.id, SET NULL | nullable — survives category deletion |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL, UNIQUE | |
| `sku` | varchar(100) | NOT NULL, UNIQUE | |
| `short_description` | text | nullable | Plain text summary |
| `description` | longtext | nullable | TinyMCE rich text HTML |
| `price` | decimal(12,2) | NOT NULL | |
| `sale_price` | decimal(12,2) | nullable | null = not on sale |
| `stock_quantity` | integer | NOT NULL, default 0 | |
| `is_active` | boolean | NOT NULL, default true | |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `slug` (unique), `sku` (unique), `category_id`, `is_active`, `deleted_at`
> **Scout:** indexed in Meilisearch on `name`, `short_description`, `sku`

---

#### `product_images`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `product_id` | uuid | FK → products.id, CASCADE | |
| `path` | varchar(500) | NOT NULL | Relative path from storage root |
| `alt_text` | varchar(255) | nullable | SEO alt text |
| `sort_order` | integer | NOT NULL, default 0 | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `product_id`, `sort_order`

---

#### `product_videos`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `product_id` | uuid | FK → products.id, CASCADE | |
| `path` | varchar(500) | NOT NULL | |
| `thumbnail_path` | varchar(500) | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `product_id`

---

### 3.3 Cart & Orders

#### `carts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id, CASCADE, nullable | null = guest cart |
| `session_id` | varchar(255) | nullable | Guest session identifier |
| `expires_at` | timestamp | NOT NULL | Guest: now +7 days. Auth user: now +30 days |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `user_id`, `session_id`, `expires_at`
> **Prune strategy:** `php artisan cart:prune` scheduled daily — deletes rows where `expires_at < now()` and cascades to `cart_items`.
> **Rule:** either `user_id` OR `session_id` must be set — enforced at service layer.

---

#### `cart_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `cart_id` | uuid | FK → carts.id, CASCADE | |
| `product_id` | uuid | FK → products.id, CASCADE | |
| `quantity` | integer | NOT NULL, default 1 | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `cart_id`, `product_id`
> **Unique:** `(cart_id, product_id)` — one row per product per cart

---

#### `orders`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `user_id` | uuid | FK → users.id, SET NULL | Preserved if user deleted |
| `status` | enum | NOT NULL, default `pending` | `pending` \| `processing` \| `shipped` \| `delivered` \| `cancelled` |
| `total_amount` | decimal(12,2) | NOT NULL | |
| `shipping_address` | jsonb | NOT NULL | Snapshot at order time — encrypted |
| `payment_method` | varchar(50) | nullable | `vnpay` (future) |
| `payment_status` | enum | NOT NULL, default `unpaid` | `unpaid` \| `paid` \| `refunded` |
| `note` | text | nullable | Customer note |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `user_id`, `status`, `payment_status`, `created_at`, `deleted_at`
> **Note:** `shipping_address` is a JSONB snapshot — not a FK to `addresses`. Protects historical data.

---

#### `order_items`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `order_id` | uuid | FK → orders.id, CASCADE | |
| `product_id` | uuid | FK → products.id, SET NULL | Preserved if product soft-deleted |
| `product_name` | varchar(255) | NOT NULL | Snapshot at order time |
| `product_sku` | varchar(100) | NOT NULL | Snapshot at order time |
| `quantity` | integer | NOT NULL | |
| `unit_price` | decimal(12,2) | NOT NULL | Snapshot at order time |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `order_id`, `product_id`

---

### 3.4 Blog

#### `blog_categories`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `parent_id` | bigint | FK → blog_categories.id, nullable | Self-referencing |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL, UNIQUE | |
| `description` | text | nullable | |
| `is_active` | boolean | NOT NULL, default true | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `slug` (unique), `parent_id`, `is_active`

---

#### `blog_posts`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | uuid | PK | |
| `author_id` | uuid | FK → users.id, SET NULL | Preserved if admin deleted |
| `blog_category_id` | bigint | FK → blog_categories.id, SET NULL | |
| `title` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL, UNIQUE | |
| `excerpt` | text | nullable | Short plain-text summary |
| `content` | longtext | NOT NULL | TinyMCE rich text HTML |
| `featured_image` | varchar(500) | nullable | |
| `status` | enum | NOT NULL, default `draft` | `draft` \| `published` \| `archived` |
| `published_at` | timestamp | nullable | Schedule future publish |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `slug` (unique), `author_id`, `blog_category_id`, `status`, `published_at`, `deleted_at`
> **Scout:** indexed in Meilisearch on `title`, `excerpt`

---

#### `blog_tags`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `name` | varchar(100) | NOT NULL, UNIQUE | |
| `slug` | varchar(100) | NOT NULL, UNIQUE | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

---

#### `blog_post_tag` *(pivot)*
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `blog_post_id` | uuid | FK → blog_posts.id, CASCADE | |
| `blog_tag_id` | bigint | FK → blog_tags.id, CASCADE | |

> **Primary Key:** `(blog_post_id, blog_tag_id)` composite

---

#### `blog_comments`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `blog_post_id` | uuid | FK → blog_posts.id, CASCADE | |
| `user_id` | uuid | FK → users.id, SET NULL | Preserved if user deleted |
| `body` | text | NOT NULL | |
| `is_approved` | boolean | NOT NULL, default false | Admin moderation flag |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `blog_post_id`, `user_id`, `is_approved`

---

### 3.5 SEO & GEO

> **Global rule for all polymorphic tables in this section:**
> `model_id` is `varchar(36)` across every table — handles both uuid (`"550e8400-..."`) and bigint (`"12"`) PKs without type conflicts. Laravel's `morphMap` resolves the correct model class from `model_type`.

---

#### `seo_meta`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `model_type` | varchar(255) | NOT NULL | e.g. `App\Models\Product` |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `meta_title` | varchar(160) | nullable | |
| `meta_description` | varchar(320) | nullable | |
| `meta_keywords` | varchar(500) | nullable | |
| `og_title` | varchar(160) | nullable | |
| `og_description` | varchar(320) | nullable | |
| `og_image` | varchar(500) | nullable | |
| `og_type` | varchar(50) | nullable, default `website` | `website` \| `article` \| `product` |
| `twitter_card` | varchar(50) | nullable, default `summary_large_image` | |
| `twitter_title` | varchar(160) | nullable | |
| `twitter_description` | varchar(320) | nullable | |
| `canonical_url` | varchar(500) | nullable | |
| `robots` | varchar(100) | nullable, default `index, follow` | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `(model_type, model_id)` composite unique

---

#### `geo_entity_profiles`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `model_type` | varchar(255) | NOT NULL | |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `ai_summary` | text | nullable | 2–3 sentence plain-text for AI ingestion |
| `key_facts` | jsonb | nullable | `[{"label":"...","value":"..."}]` |
| `faq` | jsonb | nullable | `[{"q":"...","a":"..."}]` |
| `use_cases` | text | nullable | |
| `target_audience` | varchar(255) | nullable | |
| `llm_context_hint` | text | nullable | Extra context written specifically for LLMs |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `(model_type, model_id)` composite unique

---

#### `jsonld_templates`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `schema_type` | varchar(100) | NOT NULL, UNIQUE | `Product` \| `Article` \| `BreadcrumbList` \| `FAQPage` \| `Organization` \| `WebSite` \| `CollectionPage` |
| `label` | varchar(100) | NOT NULL | Display name in Filament |
| `template` | jsonb | NOT NULL | Base JSON-LD with `{{placeholders}}` |
| `placeholders` | jsonb | nullable | Available placeholder keys and model source |
| `is_auto_generated` | boolean | NOT NULL, default true | Observer fills on model save |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `schema_type` (unique)

---

#### `jsonld_schemas`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `model_type` | varchar(255) | NOT NULL | |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `schema_type` | varchar(100) | NOT NULL | |
| `label` | varchar(100) | nullable | |
| `payload` | jsonb | NOT NULL | Final resolved JSON-LD object |
| `is_active` | boolean | NOT NULL, default true | |
| `is_auto_generated` | boolean | NOT NULL, default true | false = manual override, never overwritten by Observer |
| `sort_order` | integer | NOT NULL, default 0 | Render order in `<head>` |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `(model_type, model_id)` composite, `schema_type`, `is_active`
> **Note:** Multiple rows per entity allowed — e.g. `BlogPost` can have `Article` + `BreadcrumbList` + `FAQPage`.

---

#### Supported Schema Types per Model

| Model | Schema Types |
|---|---|
| `Product` | `Product`, `BreadcrumbList`, `FAQPage` |
| `BlogPost` | `Article`, `BreadcrumbList`, `FAQPage` |
| `Category` | `CollectionPage`, `BreadcrumbList` |
| `HomePage` (settings) | `WebSite`, `Organization` |
| `BlogIndex` (settings) | `Blog`, `BreadcrumbList` |

---

#### `llms_documents`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `name` | varchar(100) | NOT NULL, UNIQUE | e.g. `root`, `products`, `blog` |
| `slug` | varchar(100) | NOT NULL, UNIQUE | Route key e.g. `products` → `/llms-products.txt` |
| `title` | varchar(255) | NOT NULL | |
| `description` | text | nullable | |
| `scope` | varchar(50) | NOT NULL, default `full` | `index` \| `full` |
| `model_type` | varchar(255) | nullable | Scoped model — null = site-wide |
| `entry_count` | integer | NOT NULL, default 0 | |
| `last_generated_at` | timestamp | nullable | |
| `is_active` | boolean | NOT NULL, default true | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `name` (unique), `slug` (unique), `is_active`

---

#### `llms_entries`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `llms_document_id` | bigint | FK → llms_documents.id, CASCADE | |
| `model_type` | varchar(255) | NOT NULL | |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `title` | varchar(255) | NOT NULL | |
| `url` | varchar(500) | NOT NULL | Canonical URL |
| `summary` | text | nullable | From `geo_entity_profiles.ai_summary` |
| `key_facts_text` | text | nullable | Pre-flattened plain text from `geo_entity_profiles.key_facts` |
| `faq_text` | text | nullable | Pre-flattened plain text from `geo_entity_profiles.faq` |
| `is_active` | boolean | NOT NULL, default true | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `llms_document_id`, `(model_type, model_id)` composite unique, `is_active`

---

#### `redirects`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `from_path` | varchar(500) | NOT NULL, UNIQUE | e.g. `/old-product-slug` |
| `to_path` | varchar(500) | NOT NULL | e.g. `/products/new-slug` |
| `type` | smallint | NOT NULL, default `301` | `301` \| `302` |
| `hits` | integer | NOT NULL, default 0 | Track usage |
| `cache_version` | integer | NOT NULL, default 1 | Increment on any update to bust Redis cache |
| `is_active` | boolean | NOT NULL, default true | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `from_path` (unique), `is_active`
> **Cache strategy:** Full table cached in Redis under key `redirects:v{max(cache_version)}`. TTL = 60 min fallback. Invalidated immediately on any insert/update/delete via Observer. Stale cache auto-expires after 60 min worst case.

---

#### `sitemap_indexes`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `name` | varchar(100) | NOT NULL, UNIQUE | e.g. `products`, `blog`, `categories` |
| `filename` | varchar(100) | NOT NULL, UNIQUE | e.g. `sitemap-products.xml` |
| `url` | varchar(500) | NOT NULL | Absolute URL to this child sitemap |
| `entry_count` | integer | NOT NULL, default 0 | |
| `last_generated_at` | timestamp | nullable | |
| `is_active` | boolean | NOT NULL, default true | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `name` (unique), `filename` (unique), `is_active`

---

#### `sitemap_entries`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `sitemap_index_id` | bigint | FK → sitemap_indexes.id, CASCADE | |
| `model_type` | varchar(255) | NOT NULL | |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `url` | varchar(500) | NOT NULL | Absolute URL |
| `changefreq` | varchar(20) | nullable, default `weekly` | |
| `priority` | decimal(2,1) | nullable, default `0.8` | 0.1 – 1.0 |
| `last_modified` | timestamp | nullable | Synced from model `updated_at` |
| `is_active` | boolean | NOT NULL, default true | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `sitemap_index_id`, `(model_type, model_id)` composite unique, `is_active`, `last_modified`

---

### 3.6 Shared Infrastructure

#### `media`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `model_type` | varchar(255) | NOT NULL | |
| `model_id` | varchar(36) | NOT NULL | Handles uuid and bigint PKs |
| `collection` | varchar(100) | NOT NULL, default `default` | e.g. `featured`, `gallery` |
| `path` | varchar(500) | NOT NULL | Relative storage path |
| `disk` | varchar(50) | NOT NULL, default `public` | Laravel disk name |
| `mime_type` | varchar(100) | nullable | |
| `size` | bigint | nullable | File size in bytes |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `(model_type, model_id)` composite, `collection`

---

#### `activity_logs`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `log_name` | varchar(255) | nullable | e.g. `default`, `order`, `product` |
| `description` | text | NOT NULL | |
| `subject_type` | varchar(255) | nullable | |
| `subject_id` | varchar(36) | nullable | Handles uuid and bigint PKs |
| `causer_type` | varchar(255) | nullable | |
| `causer_id` | varchar(36) | nullable | Handles uuid and bigint PKs |
| `properties` | jsonb | nullable | Old/new values diff |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Indexes:** `(subject_type, subject_id)`, `(causer_type, causer_id)`, `log_name`, `created_at`
> **Package:** matches `spatie/laravel-activitylog` schema. Override `subject_id` and `causer_id` to `varchar(36)` in published migration.

---

#### `cache` *(Laravel fallback)*
| Column | Type | Notes |
|---|---|---|
| `key` | varchar(255) PK | |
| `value` | mediumtext | |
| `expiration` | integer | Unix timestamp |

> **Generated by:** `php artisan cache:table && php artisan migrate`
> **Purpose:** DB fallback if Redis is unavailable. Not used under normal operation.

---

#### `sessions` *(Laravel fallback)*
| Column | Type | Notes |
|---|---|---|
| `id` | varchar(255) PK | |
| `user_id` | varchar(36) nullable | |
| `ip_address` | varchar(45) nullable | |
| `user_agent` | text nullable | |
| `payload` | longtext | |
| `last_activity` | integer | Unix timestamp |

> **Generated by:** `php artisan session:table && php artisan migrate`
> **Purpose:** DB fallback if Redis is unavailable. Not used under normal operation.

---

## 4. Relationships Summary

```
users
 ├── has many → addresses          (user_id)
 ├── has many → carts              (user_id)
 ├── has many → orders             (user_id)
 ├── has many → blog_posts         (author_id)
 └── has many → blog_comments      (user_id)

categories
 ├── belongs to → categories       (parent_id, self-referencing)
 └── has many  → products          (category_id)

products
 ├── belongs to → categories       (category_id)
 ├── has many  → product_images    (product_id)
 ├── has many  → product_videos    (product_id)
 ├── has many  → cart_items        (product_id)
 └── has many  → order_items       (product_id)

carts
 ├── belongs to → users            (user_id, nullable)
 └── has many  → cart_items        (cart_id)

orders
 ├── belongs to → users            (user_id)
 └── has many  → order_items       (order_id)

blog_categories
 ├── belongs to → blog_categories  (parent_id, self-referencing)
 └── has many  → blog_posts        (blog_category_id)

blog_posts
 ├── belongs to → users            (author_id)
 ├── belongs to → blog_categories  (blog_category_id)
 ├── belongs to many → blog_tags   (via blog_post_tag)
 └── has many  → blog_comments     (blog_post_id)

--- Polymorphic (all use varchar(36) model_id) ---
seo_meta            → any model via (model_type, model_id)
geo_entity_profiles → any model via (model_type, model_id)
jsonld_schemas      → any model via (model_type, model_id)
llms_entries        → any model via (model_type, model_id)
sitemap_entries     → any model via (model_type, model_id)
media               → any model via (model_type, model_id)
activity_logs       → any model via (subject_type, subject_id)
```

---

## 5. Key Design Decisions

### Polymorphic `model_id` — `varchar(36)` Standard
All polymorphic tables use `varchar(36)` for `model_id`. This handles:
- UUID PKs stored as `"550e8400-e29b-41d4-a716-446655440000"`
- Bigint PKs stored as `"12"`

Laravel's `morphMap` in `AppServiceProvider` maps short aliases to model classes:
```php
Relation::morphMap([
    'product'        => \App\Models\Product::class,
    'blog_post'      => \App\Models\BlogPost::class,
    'category'       => \App\Models\Category::class,
    'blog_category'  => \App\Models\BlogCategory::class,
    'blog_tag'       => \App\Models\BlogTag::class,
]);
```
This also keeps `model_type` values short and stable — renaming a class doesn't break existing rows.

### Spatie Package Overrides
Both `spatie/laravel-activitylog` and `spatie/laravel-permission` default to `unsignedBigInteger` for morphable IDs. Publish their migrations and change to `varchar(36)` before running:
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\ActivityLog\ActivitylogServiceProvider" --tag="activitylog-migrations"
```

### Cart Expiry
`carts.expires_at` is set at creation time:
- Guest cart: `now() + 7 days`
- Authenticated cart: `now() + 30 days`
- Extended on every `cart_items` update
- Pruned daily by `php artisan cart:prune`

### Redirect Cache Invalidation
`redirects.cache_version` increments on every write. Redis cache key is `redirects:v{max_version}`. Old keys become orphaned and expire via 60-min TTL. This gives zero-downtime cache invalidation without a flush.

### Address Snapshot on Orders
`orders.shipping_address` is a JSONB snapshot — not a FK to `addresses`. Changing a delivery address after placing an order must never modify historical records.

### Price & Product Snapshot on Order Items
`order_items` stores `product_name`, `product_sku`, `unit_price` as snapshots. Past orders are immutable.

### Separate Product vs Blog Categories
`categories` and `blog_categories` are intentionally separate tables — different taxonomy, different UI, independent evolution.

### JSON-LD Two-Mode System
- `is_auto_generated = true` → Observer resolves `jsonld_templates` placeholders on model save. Zero maintenance.
- `is_auto_generated = false` → Admin manually edited in Filament. Observer never overwrites.

### LLMs Plain-Text Pre-flattening
`llms_entries` stores pre-flattened `key_facts_text` and `faq_text` as plain text — no JSON parsing at serve time. The `/llms-*.txt` controller is a single indexed query + string concatenation, keeping serve time under 50ms for 1,000+ entries.

---

## 6. Migration Order

```
001_create_users_table
002_create_password_reset_tokens_table          ← Laravel built-in
003_create_personal_access_tokens_table         ← Sanctum (tokenable_id → varchar(36))
004_create_permission_tables                    ← Spatie (model_id → varchar(36))
005_create_addresses_table
006_create_categories_table
007_create_products_table
008_create_product_images_table
009_create_product_videos_table
010_create_carts_table                          ← includes expires_at
011_create_cart_items_table
012_create_orders_table
013_create_order_items_table
014_create_blog_categories_table
015_create_blog_posts_table
016_create_blog_tags_table
017_create_blog_post_tag_table
018_create_blog_comments_table
019_create_seo_meta_table                       ← model_id varchar(36)
020_create_geo_entity_profiles_table            ← model_id varchar(36)
021_create_jsonld_templates_table
022_create_jsonld_schemas_table                 ← model_id varchar(36)
023_create_llms_documents_table
024_create_llms_entries_table                   ← model_id varchar(36)
025_create_redirects_table                      ← includes cache_version
026_create_sitemap_indexes_table
027_create_sitemap_entries_table                ← model_id varchar(36)
028_create_media_table                          ← model_id varchar(36)
029_create_activity_logs_table                  ← subject_id/causer_id varchar(36)
030_create_cache_table                          ← Laravel fallback
031_create_sessions_table                       ← Laravel fallback
```

> **Note on self-referencing tables:** `categories.parent_id` and `blog_categories.parent_id` are nullable FKs. Add the FK constraint after the table exists — PostgreSQL supports deferred constraints or simply add the FK in the same migration since the column is nullable.

---

*This document is the single source of truth for the database schema. Update it alongside every migration. Current version: 2.0*