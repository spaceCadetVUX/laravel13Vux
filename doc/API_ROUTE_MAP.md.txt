# API Route Map
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Base URL:** `https://yourdomain.com`
> **API Prefix:** `/api/v1`
> **Auth:** Laravel Sanctum (Bearer token)
> **Version:** 1.0 — April 2026

---

## Table of Contents
1. [Legend](#1-legend)
2. [Response Envelope](#2-response-envelope)
3. [Auth Routes](#3-auth-routes)
4. [Product Routes](#4-product-routes)
5. [Category Routes](#5-category-routes)
6. [Cart Routes](#6-cart-routes)
7. [Order Routes](#7-order-routes)
8. [Address Routes](#8-address-routes)
9. [Blog Routes](#9-blog-routes)
10. [Web Routes](#10-web-routes-webphp)
11. [Admin Routes](#11-admin-routes-filament)
12. [Error Responses](#12-error-responses)
13. [Rate Limits](#13-rate-limits)

---

## 1. Legend

| Symbol | Meaning |
|---|---|
| 🌐 | Public — no auth required |
| 🔐 | Customer auth required (Sanctum Bearer token) |
| 🔑 | Admin auth required (Filament session) |
| `{slug}` | URL parameter — string slug |
| `{id}` | URL parameter — UUID |
| `?param` | Optional query parameter |

---

## 2. Response Envelope

Every API response follows this envelope:

### Success
```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "errors": null,
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

### Error
```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "email": ["The email field is required."],
    "password": ["Minimum 8 characters."]
  },
  "meta": null
}
```

> `meta` is only present on paginated list responses. `errors` is only present on failed responses.

---

## 3. Auth Routes

### `POST /api/v1/auth/register` 🌐
Register a new customer account.

**Request body:**
```json
{
  "name": "Nguyen Van A",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Account created successfully",
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": "uuid",
      "name": "Nguyen Van A",
      "email": "user@example.com",
      "role": "customer",
      "email_verified_at": null,
      "created_at": "2026-04-07T10:00:00Z"
    }
  }
}
```

---

### `POST /api/v1/auth/login` 🌐
Login with email and password.

**Request body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "token": "2|xyz789...",
    "user": { "id": "uuid", "name": "...", "role": "customer" }
  }
}
```

---

### `POST /api/v1/auth/google` 🌐
Authenticate via Google OAuth. Frontend sends the Google ID token.

**Request body:**
```json
{
  "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "token": "3|google...",
    "user": { "id": "uuid", "name": "...", "email": "...", "role": "customer" },
    "is_new_user": false
  }
}
```

---

### `POST /api/v1/auth/logout` 🔐
Revoke current access token.

**Headers:** `Authorization: Bearer {token}`

**Response `200`:**
```json
{
  "success": true,
  "message": "Logged out successfully",
  "data": null
}
```

---

### `GET /api/v1/auth/me` 🔐
Get authenticated user profile.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Nguyen Van A",
    "email": "user@example.com",
    "phone": null,
    "role": "customer",
    "email_verified_at": "2026-04-07T10:00:00Z",
    "created_at": "2026-04-07T10:00:00Z"
  }
}
```

---

### `PUT /api/v1/auth/me` 🔐
Update authenticated user profile.

**Request body:**
```json
{
  "name": "Nguyen Van B",
  "phone": "0901234567"
}
```

**Response `200`:** Updated user object.

---

## 4. Product Routes

### `GET /api/v1/products` 🌐
Paginated product list with filters.

**Query parameters:**
| Param | Type | Description |
|---|---|---|
| `page` | int | Page number, default `1` |
| `per_page` | int | Items per page, default `20`, max `100` |
| `category` | string | Filter by category slug |
| `sort` | string | `price_asc` \| `price_desc` \| `newest` \| `name_asc` |
| `min_price` | decimal | Minimum price filter |
| `max_price` | decimal | Maximum price filter |
| `in_stock` | bool | Filter by stock availability |

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "name": "Smart LED Panel",
      "slug": "smart-led-panel",
      "sku": "LED-001",
      "short_description": "Casambi-compatible LED panel",
      "price": "1500000.00",
      "sale_price": "1200000.00",
      "stock_quantity": 50,
      "is_active": true,
      "category": { "id": 1, "name": "LED Panels", "slug": "led-panels" },
      "thumbnail": "https://yourdomain.com/storage/products/2026/04/img.webp",
      "created_at": "2026-04-07T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 85, "last_page": 5 }
}
```

---

### `GET /api/v1/products/{slug}` 🌐
Single product detail including images, videos, and SEO meta.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "name": "Smart LED Panel",
    "slug": "smart-led-panel",
    "sku": "LED-001",
    "short_description": "...",
    "description": "<p>Full TinyMCE HTML content...</p>",
    "price": "1500000.00",
    "sale_price": "1200000.00",
    "stock_quantity": 50,
    "is_active": true,
    "category": { "id": 1, "name": "LED Panels", "slug": "led-panels" },
    "images": [
      { "id": 1, "url": "https://...", "alt_text": "Front view", "sort_order": 0 }
    ],
    "videos": [
      { "id": 1, "url": "https://...", "thumbnail_url": "https://..." }
    ],
    "seo": {
      "meta_title": "Smart LED Panel | YourShop",
      "meta_description": "...",
      "og_image": "https://...",
      "canonical_url": "https://yourdomain.com/products/smart-led-panel"
    },
    "created_at": "2026-04-07T10:00:00Z",
    "updated_at": "2026-04-07T10:00:00Z"
  }
}
```

---

### `GET /api/v1/search` 🌐
Full-text product and blog search via Meilisearch.

**Query parameters:**
| Param | Type | Description |
|---|---|---|
| `q` | string | **Required.** Search query |
| `type` | string | `products` \| `blog` \| `all` — default `all` |
| `page` | int | Default `1` |
| `per_page` | int | Default `20` |

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "products": [ { "id": "uuid", "name": "...", "slug": "...", "thumbnail": "..." } ],
    "blog": [ { "id": "uuid", "title": "...", "slug": "...", "excerpt": "..." } ]
  },
  "meta": { "query": "LED panel", "total_products": 5, "total_blog": 2 }
}
```

---

## 5. Category Routes

### `GET /api/v1/categories` 🌐
Full category tree (nested).

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Lighting Systems",
      "slug": "lighting-systems",
      "image": "https://...",
      "sort_order": 0,
      "children": [
        { "id": 2, "name": "LED Panels", "slug": "led-panels", "children": [] }
      ]
    }
  ]
}
```

---

### `GET /api/v1/categories/{slug}` 🌐
Single category detail with its products.

**Query parameters:** `page`, `per_page`, `sort`, `min_price`, `max_price`, `in_stock`

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "LED Panels",
    "slug": "led-panels",
    "description": "...",
    "image": "https://...",
    "parent": { "id": 1, "name": "Lighting Systems", "slug": "lighting-systems" },
    "seo": { "meta_title": "...", "meta_description": "...", "og_image": "..." },
    "products": [ { "...product list..." } ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 30, "last_page": 2 }
}
```

---

## 6. Cart Routes

> Guest carts are identified by `session_id` sent as a header `X-Session-ID: {uuid}`.
> On login, call `POST /api/v1/cart/merge` to merge guest cart into authenticated cart.

---

### `GET /api/v1/cart` 🌐 / 🔐
Get current cart. Works for both guest (via `X-Session-ID`) and authenticated users.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "expires_at": "2026-04-14T10:00:00Z",
    "items": [
      {
        "id": 1,
        "product": {
          "id": "uuid",
          "name": "Smart LED Panel",
          "slug": "smart-led-panel",
          "price": "1500000.00",
          "sale_price": "1200000.00",
          "thumbnail": "https://...",
          "stock_quantity": 50
        },
        "quantity": 2,
        "subtotal": "2400000.00"
      }
    ],
    "total": "2400000.00",
    "item_count": 2
  }
}
```

---

### `POST /api/v1/cart/items` 🌐 / 🔐
Add a product to the cart. If the product already exists, quantity is incremented.

**Request body:**
```json
{
  "product_id": "uuid",
  "quantity": 1
}
```

**Response `201`:** Updated `CartResource`

---

### `PUT /api/v1/cart/items/{id}` 🌐 / 🔐
Update quantity of a cart item.

**Request body:**
```json
{
  "quantity": 3
}
```

**Response `200`:** Updated `CartResource`

---

### `DELETE /api/v1/cart/items/{id}` 🌐 / 🔐
Remove a specific item from the cart.

**Response `200`:**
```json
{
  "success": true,
  "message": "Item removed from cart",
  "data": null
}
```

---

### `DELETE /api/v1/cart` 🌐 / 🔐
Clear entire cart.

**Response `200`:**
```json
{ "success": true, "message": "Cart cleared", "data": null }
```

---

### `POST /api/v1/cart/merge` 🔐
Merge guest cart into authenticated user cart on login.

**Request body:**
```json
{
  "session_id": "guest-session-uuid"
}
```

**Response `200`:** Merged `CartResource`

---

## 7. Order Routes

### `POST /api/v1/orders` 🔐
Place a new order from the current cart.

**Request body:**
```json
{
  "address_id": "uuid",
  "note": "Please leave at door"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Order placed successfully",
  "data": {
    "id": "uuid",
    "status": "pending",
    "payment_status": "unpaid",
    "total_amount": "2400000.00",
    "shipping_address": {
      "full_name": "Nguyen Van A",
      "phone": "0901234567",
      "address_line": "123 Nguyen Hue",
      "city": "Ho Chi Minh City",
      "district": "District 1",
      "ward": "Ben Nghe"
    },
    "items": [
      {
        "product_name": "Smart LED Panel",
        "product_sku": "LED-001",
        "quantity": 2,
        "unit_price": "1200000.00",
        "subtotal": "2400000.00"
      }
    ],
    "note": "Please leave at door",
    "created_at": "2026-04-07T10:00:00Z"
  }
}
```

---

### `GET /api/v1/orders` 🔐
Authenticated customer's order history (paginated).

**Query parameters:** `page`, `per_page`, `status`

**Response `200`:** `OrderCollection`

---

### `GET /api/v1/orders/{id}` 🔐
Single order detail. Customer can only access their own orders.

**Response `200`:** Full `OrderResource` with items.

---

### `PATCH /api/v1/orders/{id}/cancel` 🔐
Cancel a pending order. Only allowed when `status = pending`.

**Response `200`:**
```json
{
  "success": true,
  "message": "Order cancelled",
  "data": { "id": "uuid", "status": "cancelled" }
}
```

---

## 8. Address Routes

### `GET /api/v1/addresses` 🔐
List all addresses for the authenticated customer.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "label": "home",
      "full_name": "Nguyen Van A",
      "phone": "0901234567",
      "address_line": "123 Nguyen Hue",
      "city": "Ho Chi Minh City",
      "district": "District 1",
      "ward": "Ben Nghe",
      "is_default": true
    }
  ]
}
```

---

### `POST /api/v1/addresses` 🔐
Create a new address.

**Request body:**
```json
{
  "label": "home",
  "full_name": "Nguyen Van A",
  "phone": "0901234567",
  "address_line": "123 Nguyen Hue",
  "city": "Ho Chi Minh City",
  "district": "District 1",
  "ward": "Ben Nghe",
  "is_default": true
}
```

**Response `201`:** `AddressResource`

---

### `PUT /api/v1/addresses/{id}` 🔐
Update an existing address.

**Response `200`:** Updated `AddressResource`

---

### `DELETE /api/v1/addresses/{id}` 🔐
Delete an address. Cannot delete if it is the only address linked to a pending order.

**Response `200`:**
```json
{ "success": true, "message": "Address deleted", "data": null }
```

---

### `PATCH /api/v1/addresses/{id}/default` 🔐
Set an address as the default.

**Response `200`:** Updated `AddressResource` with `is_default: true`.

---

## 9. Blog Routes

### `GET /api/v1/blog` 🌐
Paginated published blog post list.

**Query parameters:**
| Param | Type | Description |
|---|---|---|
| `page` | int | Default `1` |
| `per_page` | int | Default `12` |
| `category` | string | Filter by blog category slug |
| `tag` | string | Filter by tag slug |
| `sort` | string | `newest` \| `oldest` — default `newest` |

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "title": "How Casambi Mesh Works",
      "slug": "how-casambi-mesh-works",
      "excerpt": "Short plain-text summary...",
      "featured_image": "https://...",
      "author": { "id": "uuid", "name": "Admin" },
      "category": { "id": 1, "name": "Technology", "slug": "technology" },
      "tags": [ { "id": 1, "name": "Casambi", "slug": "casambi" } ],
      "published_at": "2026-04-07T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 12, "total": 48, "last_page": 4 }
}
```

---

### `GET /api/v1/blog/{slug}` 🌐
Single blog post detail with full content and SEO.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "title": "How Casambi Mesh Works",
    "slug": "how-casambi-mesh-works",
    "excerpt": "...",
    "content": "<p>Full TinyMCE HTML...</p>",
    "featured_image": "https://...",
    "author": { "id": "uuid", "name": "Admin" },
    "category": { "id": 1, "name": "Technology", "slug": "technology" },
    "tags": [ { "id": 1, "name": "Casambi", "slug": "casambi" } ],
    "seo": {
      "meta_title": "How Casambi Mesh Works | Blog",
      "meta_description": "...",
      "og_image": "https://...",
      "canonical_url": "https://yourdomain.com/blog/how-casambi-mesh-works"
    },
    "published_at": "2026-04-07T10:00:00Z",
    "updated_at": "2026-04-07T10:00:00Z"
  }
}
```

---

### `GET /api/v1/blog/categories` 🌐
Full blog category tree (nested).

**Response `200`:** Same structure as product categories tree.

---

### `GET /api/v1/blog/tags` 🌐
All blog tags.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Casambi", "slug": "casambi" },
    { "id": 2, "name": "DALI", "slug": "dali" }
  ]
}
```

---

### `GET /api/v1/blog/{slug}/comments` 🌐
Approved comments for a blog post (paginated).

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "body": "Great article!",
      "user": { "id": "uuid", "name": "Nguyen Van A" },
      "created_at": "2026-04-07T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 5 }
}
```

---

### `POST /api/v1/blog/{slug}/comments` 🔐
Submit a comment on a blog post. Requires auth. Stored as `is_approved = false` until admin approves.

**Request body:**
```json
{
  "body": "Very helpful, thank you!"
}
```

**Response `201`:**
```json
{
  "success": true,
  "message": "Comment submitted and pending approval",
  "data": { "id": 1, "body": "...", "is_approved": false }
}
```

---

## 10. Web Routes (`web.php`)

These are plain HTTP routes — not prefixed with `/api/v1`. They serve XML, plain text, and health check responses.

---

### `GET /sitemap.xml` 🌐
Master sitemap index. Lists all child sitemaps.

**Response:** `application/xml`
```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://yourdomain.com/sitemap-products.xml</loc>
    <lastmod>2026-04-07</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://yourdomain.com/sitemap-blog.xml</loc>
    <lastmod>2026-04-07</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://yourdomain.com/sitemap-categories.xml</loc>
    <lastmod>2026-04-07</lastmod>
  </sitemap>
</sitemapindex>
```

---

### `GET /sitemap-{name}.xml` 🌐
Child sitemap for a specific model type.

**Examples:** `/sitemap-products.xml`, `/sitemap-blog.xml`, `/sitemap-categories.xml`

**Response:** `application/xml`
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://yourdomain.com/products/smart-led-panel</loc>
    <lastmod>2026-04-07</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
</urlset>
```

---

### `GET /llms.txt` 🌐
Site-wide AI index. One-liner per entity — title, URL, summary.

**Response:** `text/plain`
```
# YourShop — Smart Lighting Solutions
> B2C e-commerce store for Casambi and DALI lighting products in Vietnam.

## Products
- [Smart LED Panel](https://yourdomain.com/products/smart-led-panel): Casambi-compatible LED panel for commercial use.
- [DALI Dimmer](https://yourdomain.com/products/dali-dimmer): Professional DALI-2 certified dimmer module.

## Blog
- [How Casambi Mesh Works](https://yourdomain.com/blog/how-casambi-mesh-works): Overview of Bluetooth mesh topology in Casambi systems.
```

---

### `GET /llms-full.txt` 🌐
Full AI document — all entities with facts and FAQ blocks.

**Response:** `text/plain`
```
# YourShop — Full AI Content Index

## Smart LED Panel
URL: https://yourdomain.com/products/smart-led-panel
Summary: Casambi-compatible LED panel designed for commercial and hospitality environments.
Key Facts:
  - Protocol: Casambi Bluetooth Mesh
  - Wattage: 40W
  - Warranty: 2 years
FAQ:
  Q: Is this DALI compatible?
  A: No, this model uses Casambi Bluetooth Mesh only. See our DALI range for DALI-2 products.
```

---

### `GET /llms-{slug}.txt` 🌐
Scoped AI document for a specific model type.

**Examples:** `/llms-products.txt`, `/llms-blog.txt`, `/llms-categories.txt`

**Response:** `text/plain` — same format as `llms-full.txt` but scoped to one model type.

---

### `GET /health` 🌐
Health check endpoint. Used by Docker, load balancer, and uptime monitors.

**Response `200`:**
```json
{
  "status": "ok",
  "timestamp": "2026-04-07T10:00:00Z",
  "services": {
    "database": "ok",
    "redis": "ok",
    "meilisearch": "ok",
    "horizon": "ok",
    "storage": "ok"
  }
}
```

**Response `503`** (if any service is down):
```json
{
  "status": "degraded",
  "services": {
    "database": "ok",
    "redis": "error",
    "meilisearch": "ok"
  }
}
```

---

## 11. Admin Routes (Filament)

Filament v3 generates all admin routes automatically under `/admin`. These are session-auth protected and only accessible by users with `role = admin`.

```
GET  /admin                          Dashboard
GET  /admin/products                 Product list
GET  /admin/products/create          Create product
GET  /admin/products/{id}/edit       Edit product
GET  /admin/categories               Category list
GET  /admin/orders                   Order list
GET  /admin/orders/{id}              Order detail + status management
GET  /admin/users                    Customer list
GET  /admin/blog-posts               Blog post list
GET  /admin/blog-posts/create        Create blog post (TinyMCE)
GET  /admin/blog-posts/{id}/edit     Edit blog post
GET  /admin/blog-comments            Comment moderation
GET  /admin/seo-meta                 SEO meta manager
GET  /admin/jsonld-schemas           JSON-LD schema manager
GET  /admin/jsonld-templates         JSON-LD template editor
GET  /admin/geo-profiles             GEO entity profile editor
GET  /admin/llms-documents           LLMs document registry
GET  /admin/redirects                Redirect manager (301/302)
GET  /admin/sitemap-indexes          Sitemap index manager
GET  /admin/activity-logs            Audit log viewer (read-only)
GET  /horizon                        Laravel Horizon dashboard (admin only)
```

---

## 12. Error Responses

| HTTP Code | Meaning | When |
|---|---|---|
| `200` | OK | Successful GET / PUT / PATCH / DELETE |
| `201` | Created | Successful POST (resource created) |
| `400` | Bad Request | Malformed request body |
| `401` | Unauthorized | Missing or invalid Bearer token |
| `403` | Forbidden | Authenticated but not allowed (wrong role or not owner) |
| `404` | Not Found | Resource does not exist or is soft-deleted |
| `422` | Unprocessable Entity | FormRequest validation failed |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Server Error | Unhandled exception |
| `503` | Service Unavailable | Health check failure |

### 422 Validation Error Example
```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

---

## 13. Rate Limits

| Route Group | Limit | Window |
|---|---|---|
| `POST /api/v1/auth/login` | 10 requests | 1 minute |
| `POST /api/v1/auth/register` | 5 requests | 1 minute |
| `POST /api/v1/auth/google` | 10 requests | 1 minute |
| All other `/api/v1/*` | 60 requests | 1 minute |
| `/llms*.txt` | 30 requests | 1 minute |
| `/sitemap*.xml` | 30 requests | 1 minute |
| `/health` | 60 requests | 1 minute |

> Rate limits are enforced via Laravel's built-in `throttle` middleware using Redis as the backend. Exceeded limits return `HTTP 429` with header `Retry-After: {seconds}`.

---

## Quick Reference

| Method | Route | Auth | Description |
|---|---|---|---|
| POST | `/api/v1/auth/register` | 🌐 | Register |
| POST | `/api/v1/auth/login` | 🌐 | Login |
| POST | `/api/v1/auth/google` | 🌐 | Google OAuth |
| POST | `/api/v1/auth/logout` | 🔐 | Logout |
| GET | `/api/v1/auth/me` | 🔐 | Get profile |
| PUT | `/api/v1/auth/me` | 🔐 | Update profile |
| GET | `/api/v1/products` | 🌐 | Product list |
| GET | `/api/v1/products/{slug}` | 🌐 | Product detail |
| GET | `/api/v1/search` | 🌐 | Search |
| GET | `/api/v1/categories` | 🌐 | Category tree |
| GET | `/api/v1/categories/{slug}` | 🌐 | Category + products |
| GET | `/api/v1/cart` | 🌐/🔐 | Get cart |
| POST | `/api/v1/cart/items` | 🌐/🔐 | Add to cart |
| PUT | `/api/v1/cart/items/{id}` | 🌐/🔐 | Update cart item |
| DELETE | `/api/v1/cart/items/{id}` | 🌐/🔐 | Remove cart item |
| DELETE | `/api/v1/cart` | 🌐/🔐 | Clear cart |
| POST | `/api/v1/cart/merge` | 🔐 | Merge guest cart |
| POST | `/api/v1/orders` | 🔐 | Place order |
| GET | `/api/v1/orders` | 🔐 | Order history |
| GET | `/api/v1/orders/{id}` | 🔐 | Order detail |
| PATCH | `/api/v1/orders/{id}/cancel` | 🔐 | Cancel order |
| GET | `/api/v1/addresses` | 🔐 | List addresses |
| POST | `/api/v1/addresses` | 🔐 | Create address |
| PUT | `/api/v1/addresses/{id}` | 🔐 | Update address |
| DELETE | `/api/v1/addresses/{id}` | 🔐 | Delete address |
| PATCH | `/api/v1/addresses/{id}/default` | 🔐 | Set default address |
| GET | `/api/v1/blog` | 🌐 | Blog post list |
| GET | `/api/v1/blog/{slug}` | 🌐 | Blog post detail |
| GET | `/api/v1/blog/categories` | 🌐 | Blog categories |
| GET | `/api/v1/blog/tags` | 🌐 | Blog tags |
| GET | `/api/v1/blog/{slug}/comments` | 🌐 | Post comments |
| POST | `/api/v1/blog/{slug}/comments` | 🔐 | Submit comment |
| GET | `/sitemap.xml` | 🌐 | Sitemap index |
| GET | `/sitemap-{name}.xml` | 🌐 | Child sitemap |
| GET | `/llms.txt` | 🌐 | AI index |
| GET | `/llms-full.txt` | 🌐 | AI full index |
| GET | `/llms-{slug}.txt` | 🌐 | AI scoped index |
| GET | `/health` | 🌐 | Health check |

---

*This document is the single source of truth for all API contracts. Update alongside every new controller or route change. Total endpoints: 36 API + 6 Web = 42 routes.*