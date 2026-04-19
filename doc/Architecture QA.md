# Architecture Q&A
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Stack:** Laravel 13 (API) + Nuxt 3 (Storefront)
> **Version:** 1.0 — April 2026

---

## 1. Nuxt Configuration

### Is SSR enabled or disabled?
**SSR is fully enabled.** Nuxt 3 runs in SSR mode by default via the Nitro server engine. There is no `ssr: false` in `nuxt.config.ts`.

### Is this running as SPA, SSR, or SSG?
**SSR (Server-Side Rendering)** — with selective CSR for user-specific pages.

| Mode | Pages |
|---|---|
| **Full SSR** | `/`, `/products/[slug]`, `/categories/[slug]`, `/blog`, `/blog/[slug]`, `/blog/categories/[slug]` |
| **CSR (client-side only)** | `/cart`, `/checkout`, `/auth/*`, `/account/*`, `/search` |
| **SSG** | Not used — content changes frequently, static generation not appropriate |

SSR pages are server-rendered on every request. CSR pages skip server rendering — they hydrate entirely in the browser. This is intentional: SSR pages need SEO, CSR pages are auth-protected and not indexed.

### How is data fetched in pages?

| Method | Used for | Why |
|---|---|---|
| `useAsyncData()` | All SSR pages | Runs on server, result embedded in HTML payload, no duplicate fetch on client hydration |
| `useFetch()` client-side | All CSR pages | Runs only in browser, no server involvement |
| Direct `$api()` in composables | Never called from pages directly | Always wrapped in composable, composable called from page |

**Rule:** Pages never call `$api` directly. Pages call composables (`useProduct()`, `useBlog()`...), composables call `$api`.

```ts
// ✅ Correct — SSR page
const { data } = await useAsyncData('product-ao-thun', () => useProduct().getProduct(slug))

// ✅ Correct — CSR page
const { data } = await useFetch(() => `/cart`, { headers: cartHeaders.value })

// ❌ Wrong — never do this in a page
const data = await $fetch('http://localhost:8000/api/v1/products')
```

---

## 2. How Nuxt Calls Laravel

### Base API URL
Configured via environment variable — never hardcoded.

```
# frontend/.env
NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1   ← local
NUXT_PUBLIC_API_BASE=https://yourdomain.com/api/v1  ← production
```

Accessed in code via:
```ts
const config = useRuntimeConfig()
config.public.apiBase  // → "https://yourdomain.com/api/v1"
```

### Authentication method
**Laravel Sanctum — httpOnly cookie.**

Package: `nuxt-auth-sanctum`

- Token stored in `httpOnly` cookie — not accessible by JavaScript (XSS-safe)
- `X-XSRF-TOKEN` header auto-attached on every request
- Guest cart identified by separate `X-Session-ID` cookie (UUID, 7-day TTL)
- No JWT — Sanctum token is stateful, stored server-side in `personal_access_tokens` table

### Example request flow — Product detail page load

```
1. User navigates to https://yourdomain.com/products/smart-led-panel

2. Nuxt Nitro server receives the request
   → Executes useAsyncData() on the server
   → Calls $fetch("https://yourdomain.com/api/v1/products/smart-led-panel")

3. Laravel receives GET /api/v1/products/smart-led-panel
   → Sanctum middleware checks auth (public route — skip)
   → ProductController::show() called
   → ProductService fetches product via ProductRepository
   → ProductRepository queries PostgreSQL (+ Redis cache check first)
   → ProductResource transforms model → JSON
   → Returns ApiResponse envelope with product data + seo{} + jsonld_schemas[]

4. Nuxt receives JSON response
   → useProduct().getProduct() unwraps envelope → returns data
   → useSeo(data.seo) → injects <title>, <meta>, canonical into <head>
   → JsonldRenderer injects <script type="application/ld+json"> tags
   → Page template renders full HTML

5. Nuxt sends complete HTML to browser
   → Browser displays immediately — no JS execution needed for content
   → Vue hydrates in background for interactivity (add to cart, gallery, etc.)

6. Google/AI crawler fetches same URL
   → Receives identical full HTML
   → Reads title, meta, JSON-LD, content — all present in raw HTML
```

---

## 3. Does Laravel Render Any Blade Views?

**Almost entirely API-only.** Laravel does not render Blade views for customer-facing pages.

### Routes that return non-JSON responses

| Route | Returns | Why |
|---|---|---|
| `GET /sitemap.xml` | `application/xml` | Sitemap index for Google |
| `GET /sitemap-{name}.xml` | `application/xml` | Child sitemaps (products, blog, categories) |
| `GET /llms.txt` | `text/plain` | AI crawler index |
| `GET /llms-full.txt` | `text/plain` | Full AI content document |
| `GET /llms-{slug}.txt` | `text/plain` | Scoped AI document |
| `GET /health` | `application/json` | Health check — JSON but not API envelope |
| `GET /admin/*` | Blade (Filament) | Admin panel — Filament v3 generates its own views |
| `GET /horizon` | Blade | Laravel Horizon dashboard |

### Filament (admin panel)
Filament v3 runs entirely within Laravel and renders its own Blade-based UI at `/admin`. This is **separate from the customer storefront** — Nuxt has no involvement.

```
Customer storefront → Nuxt (frontend/)
Admin panel         → Filament at /admin (backend/resources/views/vendor/filament/)
```

### `resources/views/` usage
```
backend/resources/views/
├── emails/          ← Order confirmation, password reset, email verification
└── vendor/filament/ ← Filament overrides (if any)
```
No customer-facing Blade views exist.

---

## 4. Where is Routing Handled?

### Nuxt — `frontend/pages/` (customer UI routing)

File-based routing. Every `.vue` file in `pages/` becomes a route automatically.

```
pages/index.vue                        → /
pages/products/[slug].vue              → /products/{slug}
pages/categories/[slug].vue            → /categories/{slug}
pages/cart.vue                         → /cart
pages/checkout.vue                     → /checkout
pages/blog/index.vue                   → /blog
pages/blog/[slug].vue                  → /blog/{slug}
pages/blog/categories/[slug].vue       → /blog/categories/{slug}
pages/auth/login.vue                   → /auth/login
pages/auth/register.vue                → /auth/register
pages/auth/google/callback.vue         → /auth/google/callback
pages/account/index.vue                → /account
pages/account/orders/index.vue         → /account/orders
pages/account/orders/[id].vue          → /account/orders/{id}
pages/account/addresses/index.vue      → /account/addresses
pages/[...slug].vue                    → /* (404 catch-all)
```

### Laravel `routes/api.php` (JSON API)

All customer-facing data endpoints. Prefix `/api/v1`.

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/google
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
PUT    /api/v1/auth/me
GET    /api/v1/products
GET    /api/v1/products/{slug}
GET    /api/v1/search
GET    /api/v1/categories
GET    /api/v1/categories/{slug}
GET    /api/v1/cart
POST   /api/v1/cart/items
PUT    /api/v1/cart/items/{id}
DELETE /api/v1/cart/items/{id}
DELETE /api/v1/cart
POST   /api/v1/cart/merge
POST   /api/v1/orders
GET    /api/v1/orders
GET    /api/v1/orders/{id}
PATCH  /api/v1/orders/{id}/cancel
GET    /api/v1/addresses
POST   /api/v1/addresses
PUT    /api/v1/addresses/{id}
DELETE /api/v1/addresses/{id}
PATCH  /api/v1/addresses/{id}/default
GET    /api/v1/blog
GET    /api/v1/blog/{slug}
GET    /api/v1/blog/categories
GET    /api/v1/blog/tags
GET    /api/v1/blog/{slug}/comments
POST   /api/v1/blog/{slug}/comments
```

### Laravel `routes/web.php` (non-JSON web routes)

```
GET  /sitemap.xml
GET  /sitemap-{name}.xml
GET  /llms.txt
GET  /llms-full.txt
GET  /llms-{slug}.txt
GET  /health
```

Filament routes auto-registered by Filament package under `/admin`.

### How they interact

```
Browser/Crawler
    │
    ├── yourdomain.com/*          → Nginx → Nuxt Node server (port 3000)
    │       Nuxt handles UI routing via pages/
    │       Nuxt calls Laravel internally for data
    │
    ├── yourdomain.com/api/v1/*   → Nginx → Laravel (port 8000) → routes/api.php
    │
    ├── yourdomain.com/sitemap*   → Nginx → Laravel → routes/web.php
    ├── yourdomain.com/llms*      → Nginx → Laravel → routes/web.php
    ├── yourdomain.com/health     → Nginx → Laravel → routes/web.php
    │
    └── yourdomain.com/admin/*    → Nginx → Laravel → Filament
```

**Nginx is the single entry point** — it routes traffic to either Nuxt or Laravel based on the URL prefix.

---

## 5. Deployment Setup

### Are Nuxt and Laravel deployed separately or together?
**Together in a monorepo, but as two separate processes** behind a single Nginx reverse proxy.

```
Docker Compose (single host)
├── nginx          ← reverse proxy, port 80/443
├── nuxt           ← Node.js server, port 3000
├── php-fpm        ← Laravel, port 9000
├── postgres       ← PostgreSQL, port 5432
├── redis          ← Redis, port 6379
├── meilisearch    ← Search engine, port 7700
└── horizon        ← Laravel queue worker (same PHP image)
```

### Is Nuxt running as a Node server or static build?
**Node.js server (SSR mode)** — not a static build. Nuxt runs as a persistent Node.js process via Nitro, listening on port 3000. This is required for SSR — static builds cannot server-render.

```dockerfile
# docker/nuxt/Dockerfile
FROM node:20-alpine
WORKDIR /app
COPY frontend/ .
RUN npm install && npm run build
CMD ["node", ".output/server/index.mjs"]  ← Node server, not static
```

### How are domains/subdomains structured?

**Single domain — Nginx splits traffic by URL prefix:**

```nginx
server {
    listen 80;
    server_name yourdomain.com;

    # API routes → Laravel
    location /api/ {
        proxy_pass http://php-fpm:9000;
    }

    # Sitemap, llms, health → Laravel
    location ~ ^/(sitemap|llms|health) {
        proxy_pass http://php-fpm:9000;
    }

    # Admin panel → Laravel (Filament)
    location /admin {
        proxy_pass http://php-fpm:9000;
    }

    # Everything else → Nuxt
    location / {
        proxy_pass http://nuxt:3000;
    }
}
```

No subdomain split (no `api.yourdomain.com`). Everything runs under one domain — simpler CORS config, same cookie domain.

---

## 6. Architecture Classification

> **This project is a Decoupled SSR Monorepo** — a Laravel 13 headless API backend paired with a Nuxt 3 SSR frontend, deployed together behind a single Nginx reverse proxy, with Filament v3 as a separate admin panel on the same Laravel instance.

More precisely: **Headless Commerce architecture with SSR storefront.**

---

## 7. Potential Issues & Misconfigurations

### SEO Risks

| Risk | Severity | Detail | Mitigation |
|---|---|---|---|
| SSR page forgetting `useSeo()` | 🔴 High | Page renders without `<title>` or `<meta>` — Google sees blank meta | Checklist in Frontend Build Plan — verify every SSR page |
| SSR page forgetting `<JsonldRenderer>` | 🔴 High | No structured data — Google cannot parse product/article schema | Same checklist |
| Nuxt falls back to CSR on error | 🟡 Medium | If Nuxt server crashes mid-render, browser may receive empty shell | Monitor Nuxt process — use PM2 or Docker restart policy |
| Canonical URL mismatch | 🟡 Medium | If `canonical_url` in DB differs from actual URL, Google may ignore page | Laravel Observer must sync canonical on every slug change |
| `/account/*` accidentally indexed | 🟡 Medium | If `robots.txt` is misconfigured, Google indexes auth-protected pages | Verify `robots.txt` in `frontend/public/` blocks `/account/`, `/cart`, `/checkout` |
| Duplicate content from pagination | 🟠 Low-Medium | `/categories/led-panels?page=2` indexed without canonical | Add `<link rel="canonical">` pointing to page 1 on paginated pages |

---

### Performance Bottlenecks

| Bottleneck | Severity | Detail | Mitigation |
|---|---|---|---|
| Extra network hop (Nuxt → Laravel) | 🟡 Medium | Every SSR page render requires Nuxt to call Laravel API — adds ~50-150ms TTFB vs Blade | `spatie/laravel-responsecache` + Redis cache on Laravel side |
| N+1 queries in API responses | 🔴 High | If repositories don't eager-load relations, each product card triggers extra queries | `with(['category', 'seoMeta', 'media'])` in all list queries — N+1 Detection already implemented |
| No CDN for images | 🟡 Medium | Images served directly from Laravel storage — slow for users far from server | Add Cloudflare or similar CDN in front of storage |
| Nuxt bundle size | 🟠 Low-Medium | Large JS bundle slows Time to Interactive | Audit with `nuxt analyze` — lazy-load heavy components |
| Missing `useAsyncData` cache key | 🟡 Medium | If cache key is wrong, same data fetched twice (server + client) | Always use unique descriptive key: `product-${slug}` not `product` |
| Meilisearch cold start | 🟠 Low | First search query after idle may be slow | Keep Meilisearch warm — health check ping every 5 minutes |

---

### Security Concerns

| Concern | Severity | Detail | Mitigation |
|---|---|---|---|
| CORS misconfiguration | 🔴 High | If `SANCTUM_STATEFUL_DOMAINS` not set correctly, auth cookies won't attach | Set `SANCTUM_STATEFUL_DOMAINS=yourdomain.com` in backend `.env` |
| `X-Session-ID` spoofing (guest cart) | 🟡 Medium | Attacker sends arbitrary UUID to access another guest cart | Guest carts contain no sensitive data — acceptable risk. Carts expire in 7 days. |
| Token in localStorage (if misconfigured) | 🔴 High | If `nuxt-auth-sanctum` falls back to localStorage, token is XSS-vulnerable | Verify token is in `httpOnly` cookie only — never in `localStorage` |
| Admin panel exposed at `/admin` | 🟡 Medium | Filament at `/admin` is publicly accessible URL — brute-force risk | Rate limiting already on auth routes. Add IP allowlist for `/admin` in Nginx if needed. |
| Encrypted fields accessed raw | 🔴 High | If `users.email` or `addresses.phone` read directly from DB (not via accessor), data is exposed as ciphertext — or worse, logged | Always use model accessor for encrypted fields — enforced in CLAUDE.md |
| `UiRichText` XSS via TinyMCE content | 🟡 Medium | Blog content rendered via `v-html` — if not sanitized, stored XSS possible | Sanitize HTML server-side before storing (Laravel), and client-side before rendering (`DOMPurify`) |
| HTTPS not enforced | 🔴 High | If Nginx doesn't redirect HTTP → HTTPS, Sanctum cookies without `Secure` flag may be sent over plain HTTP | Force HTTPS in Nginx config. Set `SESSION_SECURE_COOKIE=true` in Laravel `.env` |
| Horizon dashboard public | 🟡 Medium | `/horizon` accessible without IP restriction | Restrict `/horizon` to admin IPs in `HorizonServiceProvider::gate()` |

---

*Last updated: April 2026. Review this document when adding new pages, routes, or deployment changes.*
