# Frontend Architecture — Nuxt 3 Storefront
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Frontend Framework:** Nuxt 3
> **Repo Structure:** Monorepo (`backend/` + `frontend/`)
> **Version:** 1.0 — April 2026

---

## Table of Contents
1. [Stack Overview](#1-stack-overview)
2. [Monorepo Structure](#2-monorepo-structure)
3. [Frontend Folder Structure](#3-frontend-folder-structure)
4. [SSR Strategy](#4-ssr-strategy)
5. [SEO & GEO Integration](#5-seo--geo-integration)
6. [Authentication Flow](#6-authentication-flow)
7. [API Communication](#7-api-communication)
8. [Pages & Routing](#8-pages--routing)
9. [Component Architecture](#9-component-architecture)
10. [State Management](#10-state-management)
11. [Performance Strategy](#11-performance-strategy)
12. [UI & Styling](#12-ui--styling)
13. [Environment Variables](#13-environment-variables)
14. [NPM Packages](#14-npm-packages)
15. [Key Architecture Rules](#15-key-architecture-rules)

---

## 1. Stack Overview

| Layer | Choice | Notes |
|---|---|---|
| Framework | Nuxt 3 | Full SSR via Nitro server engine |
| UI Library | Nuxt UI v3 | Free, Tailwind-based, 50+ components |
| Styling | Tailwind CSS v4 | Utility-first, zero runtime |
| SSR Mode | Full SSR | All pages server-rendered for SEO/GEO |
| Image optimization | `@nuxt/image` | Automatic WebP, lazy load, srcset |
| SEO / Head | `useHead()` + `useSeoMeta()` | Nuxt built-in, zero config |
| Auth | `nuxt-auth-sanctum` | Cookie-based Sanctum token, free |
| HTTP client | `$fetch` / `useFetch()` | Nuxt built-in, SSR-aware |
| State management | `useState()` | Nuxt built-in — Pinia if complexity grows |
| Rich text rendering | `@nuxtjs/mdc` | Renders TinyMCE HTML safely |
| Repo structure | Monorepo | `backend/` + `frontend/` in one repo |

---

## 2. Monorepo Structure

```
/ (root)
├── backend/                         ← Laravel 13 (API + Admin + SEO/GEO routes)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   └── ...
│
├── frontend/                        ← Nuxt 3 (customer-facing storefront)
│   ├── assets/
│   ├── components/
│   ├── composables/
│   ├── layouts/
│   ├── middleware/
│   ├── pages/
│   ├── plugins/
│   ├── public/
│   ├── server/
│   ├── stores/
│   ├── types/
│   ├── utils/
│   ├── app.vue
│   ├── nuxt.config.ts
│   ├── tailwind.config.ts
│   └── package.json
│
├── docker/
│   ├── nginx/
│   ├── php/
│   └── nuxt/
│       └── Dockerfile
├── docker-compose.yml
├── .env                             ← root env (Docker vars)
├── ARCHITECTURE.md
├── ERD.md
├── FOLDER_STRUCTURE.md
├── FRONTEND_ARCHITECTURE.md
├── API_ROUTE_MAP.md
├── CLAUDE.md
└── README.md
```

---

## 3. Frontend Folder Structure

```
frontend/
├── assets/
│   ├── css/
│   │   └── main.css                 ← Tailwind base + custom CSS variables
│   └── fonts/                       ← self-hosted fonts if any
│
├── components/
│   ├── App/
│   │   ├── AppHeader.vue
│   │   ├── AppFooter.vue
│   │   ├── AppNav.vue
│   │   └── AppBreadcrumb.vue
│   ├── Product/
│   │   ├── ProductCard.vue          ← used in lists and category pages
│   │   ├── ProductGrid.vue
│   │   ├── ProductDetail.vue
│   │   ├── ProductImages.vue        ← image gallery with zoom
│   │   ├── ProductVideo.vue
│   │   └── ProductPrice.vue        ← handles price vs sale_price display
│   ├── Category/
│   │   ├── CategoryTree.vue         ← nested sidebar nav
│   │   └── CategoryBreadcrumb.vue
│   ├── Cart/
│   │   ├── CartDrawer.vue           ← slide-out cart sidebar
│   │   ├── CartItem.vue
│   │   ├── CartSummary.vue
│   │   └── CartEmpty.vue
│   ├── Order/
│   │   ├── OrderList.vue
│   │   ├── OrderCard.vue
│   │   └── OrderDetail.vue
│   ├── Address/
│   │   ├── AddressList.vue
│   │   ├── AddressCard.vue
│   │   └── AddressForm.vue
│   ├── Blog/
│   │   ├── BlogCard.vue
│   │   ├── BlogGrid.vue
│   │   ├── BlogDetail.vue
│   │   ├── BlogComments.vue
│   │   └── BlogCommentForm.vue
│   ├── Search/
│   │   ├── SearchBar.vue
│   │   ├── SearchResults.vue
│   │   └── SearchEmpty.vue
│   ├── Seo/
│   │   └── JsonldRenderer.vue       ← injects JSON-LD <script> tags from API
│   └── Ui/
│       ├── UiPagination.vue
│       ├── UiAlert.vue
│       ├── UiBadge.vue
│       ├── UiSpinner.vue
│       └── UiRichText.vue           ← safely renders TinyMCE HTML
│
├── composables/
│   ├── useApi.ts                    ← base $fetch wrapper with envelope handling
│   ├── useAuth.ts                   ← login, logout, register, me
│   ├── useCart.ts                   ← cart state + API actions
│   ├── useProduct.ts                ← fetch product list + detail
│   ├── useCategory.ts               ← fetch category tree + detail
│   ├── useOrder.ts                  ← place order, list, detail
│   ├── useAddress.ts                ← CRUD addresses
│   ├── useBlog.ts                   ← fetch blog list + detail + comments
│   ├── useSearch.ts                 ← search with debounce
│   └── useSeo.ts                   ← apply seo meta from API response
│
├── layouts/
│   ├── default.vue                  ← AppHeader + AppFooter + CartDrawer
│   ├── minimal.vue                  ← auth pages (login, register)
│   └── account.vue                  ← customer account pages with sidebar
│
├── middleware/
│   ├── auth.ts                      ← redirect to /login if not authenticated
│   └── guest.ts                     ← redirect to / if already authenticated
│
├── pages/
│   ├── index.vue                    ← homepage
│   ├── search.vue                   ← search results
│   ├── products/
│   │   └── [slug].vue               ← product detail
│   ├── categories/
│   │   └── [slug].vue               ← category + product list
│   ├── cart.vue                     ← cart page
│   ├── checkout.vue                 ← checkout form + address selection
│   ├── blog/
│   │   ├── index.vue                ← blog list
│   │   ├── [slug].vue               ← blog post detail
│   │   └── categories/
│   │       └── [slug].vue           ← blog category filtered list
│   ├── auth/
│   │   ├── login.vue
│   │   ├── register.vue
│   │   └── google/
│   │       └── callback.vue         ← Google OAuth callback handler
│   ├── account/
│   │   ├── index.vue                ← profile overview
│   │   ├── orders/
│   │   │   ├── index.vue            ← order history
│   │   │   └── [id].vue             ← order detail
│   │   └── addresses/
│   │       └── index.vue            ← address management
│   └── [...slug].vue                ← 404 catch-all
│
├── plugins/
│   └── api.client.ts                ← global $api plugin with base URL
│
├── public/
│   ├── favicon.ico
│   ├── robots.txt                   ← managed here (sitemap ref points to Laravel)
│   └── og-default.jpg               ← default OG image fallback
│
├── server/
│   └── (empty at launch)            ← Nitro server routes if needed later
│
├── stores/
│   └── cart.ts                      ← Pinia cart store (if useState insufficient)
│
├── types/
│   ├── api.ts                       ← API envelope type definitions
│   ├── product.ts
│   ├── category.ts
│   ├── cart.ts
│   ├── order.ts
│   ├── blog.ts
│   └── seo.ts
│
├── utils/
│   ├── currency.ts                  ← formatCurrency(amount) → "1.500.000 ₫"
│   ├── date.ts                      ← formatDate(iso) → "07/04/2026"
│   └── slug.ts                      ← helper for slug generation client-side
│
├── app.vue                          ← root component with <NuxtLayout> + <NuxtPage>
├── nuxt.config.ts
├── tailwind.config.ts
├── tsconfig.json
└── package.json
```

---

## 4. SSR Strategy

### Mode: Full SSR (default)
Every page is server-rendered by Nitro on first request. The client receives complete HTML including all meta tags, JSON-LD, and product content. Google and AI crawlers see the full page without executing JavaScript.

### Data fetching per page type

| Page type | Nuxt method | Notes |
|---|---|---|
| Product list | `useAsyncData()` | Cached, server-rendered |
| Product detail | `useAsyncData()` | Cached per slug |
| Category page | `useAsyncData()` | Cached per slug |
| Blog list | `useAsyncData()` | Cached, server-rendered |
| Blog post | `useAsyncData()` | Cached per slug |
| Cart | `useFetch()` client-side | Cart is user-specific — no cache |
| Account pages | `useFetch()` client-side | Auth-protected, no cache |
| Search results | `useFetch()` client-side | Dynamic, no cache |

### Caching strategy (Nuxt side)
```ts
// pages/products/[slug].vue
const { data: product } = await useAsyncData(
  `product-${slug}`,
  () => $api(`/products/${slug}`),
  {
    getCachedData: (key) => nuxtApp.payload.data[key],   // use SSR payload on client
  }
)
```

---

## 5. SEO & GEO Integration

### How JSON-LD reaches the page

```
Laravel API → GET /products/{slug}
  → response includes seo{} object + jsonld_schemas[]
    → Nuxt useSeoMeta() sets <meta> tags
    → JsonldRenderer.vue injects <script type="application/ld+json"> per schema
```

### `useSeo.ts` composable
```ts
export const useSeo = (seo: SeoMeta) => {
  useSeoMeta({
    title: seo.meta_title,
    description: seo.meta_description,
    ogTitle: seo.og_title ?? seo.meta_title,
    ogDescription: seo.og_description ?? seo.meta_description,
    ogImage: seo.og_image,
    ogType: seo.og_type ?? 'website',
    twitterCard: seo.twitter_card ?? 'summary_large_image',
    robots: seo.robots ?? 'index, follow',
  })

  if (seo.canonical_url) {
    useHead({ link: [{ rel: 'canonical', href: seo.canonical_url }] })
  }
}
```

### `JsonldRenderer.vue`
```vue
<script setup lang="ts">
const props = defineProps<{ schemas: JsonldSchema[] }>()

useHead({
  script: props.schemas
    .filter(s => s.is_active)
    .sort((a, b) => a.sort_order - b.sort_order)
    .map(s => ({
      type: 'application/ld+json',
      innerHTML: JSON.stringify(s.payload),
    }))
})
</script>
<template><slot /></template>
```

### `robots.txt` (frontend/public/robots.txt)
```
User-agent: *
Allow: /
Disallow: /account/
Disallow: /cart
Disallow: /checkout
Sitemap: https://yourdomain.com/sitemap.xml
```

### GEO — llms.txt
Served entirely by Laravel at `/llms.txt`, `/llms-full.txt`, `/llms-products.txt`, `/llms-blog.txt`.
Nuxt does not touch these routes — Nginx routes them directly to Laravel.

---

## 6. Authentication Flow

### Package: `nuxt-auth-sanctum`
- Stores Sanctum token in `httpOnly` cookie (secure, not accessible by JS)
- Auto-attaches `X-XSRF-TOKEN` header on every request
- Provides `useSanctumAuth()` composable

### Login flow
```
User submits login form
  → POST /api/v1/auth/login (Laravel)
  → Token returned in response body
  → nuxt-auth-sanctum stores token in httpOnly cookie
  → Nuxt redirects to /account
  → POST /api/v1/cart/merge (merge guest cart)
```

### Google OAuth flow
```
User clicks "Login with Google"
  → Nuxt redirects to Google consent screen
  → Google redirects to /auth/google/callback?code=...
  → Nuxt callback page sends code to POST /api/v1/auth/google
  → Laravel exchanges code via Socialite, returns token
  → nuxt-auth-sanctum stores token
  → Redirect to /account
```

### Route protection
```ts
// middleware/auth.ts
export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated } = useSanctumAuth()
  if (!isAuthenticated.value) {
    return navigateTo('/auth/login')
  }
})
```

Applied to account pages:
```ts
// pages/account/index.vue
definePageMeta({ middleware: 'auth' })
```

---

## 7. API Communication

### Base composable: `useApi.ts`
```ts
export const useApi = () => {
  const config = useRuntimeConfig()

  const $api = $fetch.create({
    baseURL: config.public.apiBase,
    headers: { Accept: 'application/json' },
    onResponseError({ response }) {
      if (response.status === 401) navigateTo('/auth/login')
      if (response.status === 404) throw createError({ statusCode: 404 })
    },
  })

  return { $api }
}
```

### Response envelope unwrapping
All API responses follow the standard envelope. Composables unwrap `data` automatically:
```ts
// composables/useProduct.ts
export const useProduct = () => {
  const getProduct = async (slug: string) => {
    const response = await $api<ApiResponse<Product>>(`/products/${slug}`)
    return response.data          // unwrap envelope
  }
  return { getProduct }
}
```

### Guest cart session
```ts
// composables/useCart.ts
const sessionId = useCookie('cart_session_id', {
  default: () => crypto.randomUUID(),
  maxAge: 60 * 60 * 24 * 7,      // 7 days
})

const headers = computed(() => ({
  'X-Session-ID': sessionId.value
}))
```

---

## 8. Pages & Routing

| URL | Page file | Auth | SSR |
|---|---|---|---|
| `/` | `pages/index.vue` | 🌐 | Full SSR |
| `/products/{slug}` | `pages/products/[slug].vue` | 🌐 | Full SSR |
| `/categories/{slug}` | `pages/categories/[slug].vue` | 🌐 | Full SSR |
| `/search` | `pages/search.vue` | 🌐 | CSR |
| `/blog` | `pages/blog/index.vue` | 🌐 | Full SSR |
| `/blog/{slug}` | `pages/blog/[slug].vue` | 🌐 | Full SSR |
| `/blog/categories/{slug}` | `pages/blog/categories/[slug].vue` | 🌐 | Full SSR |
| `/cart` | `pages/cart.vue` | 🌐 | CSR |
| `/checkout` | `pages/checkout.vue` | 🔐 | CSR |
| `/auth/login` | `pages/auth/login.vue` | Guest only | CSR |
| `/auth/register` | `pages/auth/register.vue` | Guest only | CSR |
| `/auth/google/callback` | `pages/auth/google/callback.vue` | 🌐 | CSR |
| `/account` | `pages/account/index.vue` | 🔐 | CSR |
| `/account/orders` | `pages/account/orders/index.vue` | 🔐 | CSR |
| `/account/orders/{id}` | `pages/account/orders/[id].vue` | 🔐 | CSR |
| `/account/addresses` | `pages/account/addresses/index.vue` | 🔐 | CSR |

> SSR pages get full meta tags, JSON-LD, and Open Graph rendered in the initial HTML.
> CSR pages (cart, account) are user-specific and not indexed — no SSR needed.

---

## 9. Component Architecture

### Rule: Smart composables, dumb components
- **Pages** — fetch data via composables, pass to components as props
- **Components** — receive props, emit events, no direct API calls
- **Composables** — own all API calls and business logic

```
pages/products/[slug].vue
  → useProduct().getProduct(slug)
  → <ProductDetail :product="product" />
      → <ProductImages :images="product.images" />
      → <ProductPrice :price="product.price" :sale-price="product.sale_price" />
      → <JsonldRenderer :schemas="product.jsonld_schemas" />
      → useSeo(product.seo)
```

### Component naming
- `App*` — global layout components (`AppHeader`, `AppFooter`)
- `Product*` — product domain components (`ProductCard`, `ProductGrid`)
- `Blog*` — blog domain components (`BlogCard`, `BlogDetail`)
- `Ui*` — generic reusable UI primitives (`UiPagination`, `UiBadge`)
- No prefix — page-level components used once

---

## 10. State Management

### Approach: `useState()` first, Pinia only if needed

| State | Location | Notes |
|---|---|---|
| Cart items + total | `useState('cart')` | Shared across components |
| Auth user | `useSanctumAuth()` | Managed by nuxt-auth-sanctum |
| Category tree | `useState('categories')` | Fetched once, reused globally |
| Search query | Local `ref()` in search page | No global state needed |
| UI (drawer open/close) | Local `ref()` in component | No global state needed |

### Cart store (Pinia — `stores/cart.ts`)
Used if cart logic becomes complex (promo codes, VNPay integration):
```ts
export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([])
  const total = computed(() =>
    items.value.reduce((sum, i) => sum + i.quantity * parseFloat(i.product.sale_price ?? i.product.price), 0)
  )
  return { items, total }
})
```

---

## 11. Performance Strategy

### Core Web Vitals targets
| Metric | Target |
|---|---|
| LCP (Largest Contentful Paint) | < 2.5s |
| FID / INP (Interaction) | < 100ms |
| CLS (Cumulative Layout Shift) | < 0.1 |

### Techniques

**Images** — `@nuxt/image` with automatic WebP conversion and lazy loading:
```vue
<NuxtImg
  :src="product.thumbnail"
  :alt="product.name"
  width="400"
  height="400"
  format="webp"
  loading="lazy"
  sizes="sm:100vw md:50vw lg:400px"
/>
```

**Fonts** — self-hosted or `@nuxtjs/google-fonts` with `display: swap`

**Bundle splitting** — automatic per Nuxt 3 / Vite defaults

**API response caching** — `useAsyncData` with keyed cache prevents duplicate fetches during navigation

**Prefetching** — Nuxt auto-prefetches linked pages on hover via `<NuxtLink>`

**No layout shift** — always define explicit `width` + `height` on `<NuxtImg>`

---

## 12. UI & Styling

### Nuxt UI v3 + Tailwind CSS v4

**Theme config** (`nuxt.config.ts`):
```ts
export default defineNuxtConfig({
  modules: ['@nuxt/ui', '@nuxt/image', 'nuxt-auth-sanctum'],
  ui: {
    theme: {
      colors: ['primary', 'neutral'],
    }
  },
  css: ['~/assets/css/main.css'],
})
```

**Custom color tokens** (`assets/css/main.css`):
```css
:root {
  --color-primary: /* your brand color */;
}
```

### Vietnamese currency formatting (`utils/currency.ts`)
```ts
export const formatCurrency = (amount: string | number): string => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(Number(amount))
}
// → "1.500.000 ₫"
```

---

## 13. Environment Variables

### `frontend/.env`
```
NUXT_PUBLIC_API_BASE=https://yourdomain.com/api/v1
NUXT_PUBLIC_APP_NAME=YourShop
NUXT_PUBLIC_APP_URL=https://yourdomain.com
NUXT_PUBLIC_GOOGLE_CLIENT_ID=your-google-client-id
```

### `nuxt.config.ts` runtime config
```ts
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE,
      appName: process.env.NUXT_PUBLIC_APP_NAME,
      appUrl: process.env.NUXT_PUBLIC_APP_URL,
      googleClientId: process.env.NUXT_PUBLIC_GOOGLE_CLIENT_ID,
    }
  }
})
```

---

## 14. NPM Packages

```bash
# Core
npx nuxi init frontend
cd frontend

# UI + Styling
npm install @nuxt/ui tailwindcss

# Image optimization
npm install @nuxt/image

# Auth (Sanctum)
npm install nuxt-auth-sanctum

# State (if Pinia needed)
npm install @pinia/nuxt pinia

# Dev tools
npm install -D @nuxt/devtools typescript vue-tsc
```

### `nuxt.config.ts` modules
```ts
modules: [
  '@nuxt/ui',
  '@nuxt/image',
  'nuxt-auth-sanctum',
  '@pinia/nuxt',        // add only if Pinia store needed
]
```

---

## 15. Key Architecture Rules

### SSR-first — account and cart pages are the only exceptions
All public pages must be fully server-rendered. Never use `client-only` wrappers on product or blog content.

### No API calls inside components
All data fetching lives in composables. Components receive props only.

### Always use `NuxtLink` for internal navigation
Never use `<a href>` for internal links — `NuxtLink` enables client-side navigation and prefetching.

### Always use `NuxtImg` for images
Never use raw `<img>` tags — `NuxtImg` handles WebP conversion, lazy loading, and srcset automatically.

### SEO applied on every SSR page
Every SSR page must call `useSeo(response.seo)` and render `<JsonldRenderer :schemas="response.jsonld_schemas" />`. No exceptions.

### Vietnamese locale
```ts
// utils/date.ts
export const formatDate = (iso: string): string =>
  new Intl.DateTimeFormat('vi-VN').format(new Date(iso))
// → "07/04/2026"
```

### CORS — backend must whitelist the Nuxt origin
```php
// backend/config/cors.php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:3000'),
],
```

---

*This document is the single source of truth for the Nuxt 3 frontend architecture. Update it alongside every new page, composable, or package addition.*