# Frontend Build Plan — Nuxt 3 Storefront
> **Project:** Laravel 13 B2C E-commerce + Blog
> **Framework:** Nuxt 3 + Nuxt UI v3 + Tailwind CSS v4
> **Version:** 1.0 — April 2026
> **Reference:** `doc/Frontend Architecture — Nuxt 3 Storefront.md` | `doc/API_ROUTE_MAP.md`

---

## Rules Before Writing Any Code

| Rule | Detail |
|---|---|
| Pages fetch via composables | Never call `$api` directly in `<script setup>` of a page |
| Components are dumb | Receive props, emit events — no API calls |
| SSR pages use `useAsyncData()` | Public pages: products, categories, blog |
| CSR pages use `useFetch()` client-side | Auth-protected pages: cart, account, checkout |
| SEO on every SSR page | `useSeo(data.seo)` + `<JsonldRenderer>` — mandatory |
| No raw `<img>` | Always `<NuxtImg>` with explicit `width` + `height` |
| No raw `<a href>` for internal links | Always `<NuxtLink>` |
| No hardcoded API URL | Always `useRuntimeConfig().public.apiBase` |
| No `console.log` in committed code | Use Nuxt devtools instead |
| Currency via util | Always `formatCurrency()` from `utils/currency.ts` |
| Date via util | Always `formatDate()` from `utils/date.ts` |

---

## Phase Overview

| Phase | Name | Deliverable | SSR/CSR |
|---|---|---|---|
| **P1** | Project Setup | Nuxt project + config + packages | — |
| **P2** | Types & Utils | TypeScript types + utility functions | — |
| **P3** | API Layer | `useApi.ts` + envelope handling | — |
| **P4** | Layouts & Shell | Header, Footer, layouts, middleware | — |
| **P5** | Auth | Login, Register, Google OAuth | CSR |
| **P6** | Products | Product list, detail, category pages | SSR |
| **P7** | Cart | Guest + auth cart, CartDrawer | CSR |
| **P8** | Checkout & Orders | Place order, order history, detail | CSR |
| **P9** | Addresses | CRUD address management | CSR |
| **P10** | Blog | Blog list, detail, categories, tags | SSR |
| **P11** | Search | Full-text search with debounce | CSR |
| **P12** | Account | Profile, sidebar account layout | CSR |
| **P13** | SEO Layer | `useSeo`, `JsonldRenderer`, robots.txt | SSR |
| **P14** | Performance | Image optimization, CWV audit | — |

---

## P1 — Project Setup

### Goal
Initialize the Nuxt 3 project with all required packages, config, and folder structure.

### Commands
```bash
npx nuxi@latest init frontend
cd frontend

# UI + Styling
npm install @nuxt/ui

# Image optimization
npm install @nuxt/image

# Auth
npm install nuxt-auth-sanctum

# State (Pinia — cart store)
npm install @pinia/nuxt pinia

# Dev tools
npm install -D @nuxt/devtools typescript vue-tsc
```

### Files to create

**`frontend/nuxt.config.ts`**
```ts
export default defineNuxtConfig({
  devtools: { enabled: true },

  modules: [
    '@nuxt/ui',
    '@nuxt/image',
    'nuxt-auth-sanctum',
    '@pinia/nuxt',
  ],

  css: ['~/assets/css/main.css'],

  ui: {
    theme: {
      colors: ['primary', 'neutral'],
    }
  },

  image: {
    format: ['webp'],
  },

  sanctum: {
    baseUrl: process.env.NUXT_PUBLIC_API_BASE,
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE,
      appName: process.env.NUXT_PUBLIC_APP_NAME,
      appUrl: process.env.NUXT_PUBLIC_APP_URL,
      googleClientId: process.env.NUXT_PUBLIC_GOOGLE_CLIENT_ID,
    }
  },

  compatibilityDate: '2026-04-01',
})
```

**`frontend/.env`**
```
NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1
NUXT_PUBLIC_APP_NAME=YourShop
NUXT_PUBLIC_APP_URL=http://localhost:3000
NUXT_PUBLIC_GOOGLE_CLIENT_ID=your-google-client-id
```

**`frontend/assets/css/main.css`**
```css
@import "tailwindcss";

:root {
  --color-primary: oklch(0.5 0.2 250); /* replace with brand color */
}
```

**`frontend/app.vue`**
```vue
<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
```

### Folder structure to create (empty files)
```
frontend/
├── assets/css/main.css
├── components/App/
├── components/Product/
├── components/Category/
├── components/Cart/
├── components/Order/
├── components/Address/
├── components/Blog/
├── components/Search/
├── components/Seo/
├── components/Ui/
├── composables/
├── layouts/
├── middleware/
├── pages/
├── plugins/
├── public/
├── stores/
├── types/
└── utils/
```

### Checklist
- [ ] `npm run dev` starts without errors
- [ ] Nuxt devtools accessible at `/__nuxt_devtools__`
- [ ] `@nuxt/ui` components resolve (try `<UButton>` in `app.vue`)

---

## P2 — Types & Utils

### Goal
Define all TypeScript types matching the API envelope, and utility functions.

### Files to create

**`frontend/types/api.ts`** — API envelope
```ts
export interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
  meta: PaginationMeta | null
}

export interface PaginationMeta {
  page: number
  per_page: number
  total: number
  last_page: number
}
```

**`frontend/types/seo.ts`**
```ts
export interface SeoMeta {
  meta_title: string
  meta_description: string
  og_title?: string
  og_description?: string
  og_image?: string
  og_type?: string
  twitter_card?: string
  canonical_url?: string
  robots?: string
}

export interface JsonldSchema {
  id: number
  schema_type: string
  payload: Record<string, unknown>
  is_active: boolean
  sort_order: number
}
```

**`frontend/types/product.ts`**
```ts
export interface ProductListItem {
  id: string
  name: string
  slug: string
  sku: string
  short_description: string
  price: string
  sale_price: string | null
  stock_quantity: number
  is_active: boolean
  category: { id: number; name: string; slug: string }
  thumbnail: string
  created_at: string
}

export interface ProductDetail extends ProductListItem {
  description: string
  images: ProductImage[]
  videos: ProductVideo[]
  seo: SeoMeta
  jsonld_schemas: JsonldSchema[]
  updated_at: string
}

export interface ProductImage {
  id: number
  url: string
  alt_text: string
  sort_order: number
}

export interface ProductVideo {
  id: number
  url: string
  thumbnail_url: string
}
```

**`frontend/types/category.ts`**
```ts
export interface Category {
  id: number
  name: string
  slug: string
  description?: string
  image?: string
  parent?: { id: number; name: string; slug: string }
  children: Category[]
  seo?: SeoMeta
  jsonld_schemas?: JsonldSchema[]
}
```

**`frontend/types/cart.ts`**
```ts
export interface Cart {
  id: string
  expires_at: string
  items: CartItem[]
  total: string
  item_count: number
}

export interface CartItem {
  id: number
  product: {
    id: string
    name: string
    slug: string
    price: string
    sale_price: string | null
    thumbnail: string
    stock_quantity: number
  }
  quantity: number
  subtotal: string
}
```

**`frontend/types/order.ts`**
```ts
export type OrderStatus = 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled'
export type PaymentStatus = 'unpaid' | 'paid' | 'refunded'

export interface Order {
  id: string
  status: OrderStatus
  payment_status: PaymentStatus
  total_amount: string
  shipping_address: ShippingAddress
  items: OrderItem[]
  note?: string
  created_at: string
}

export interface ShippingAddress {
  full_name: string
  phone: string
  address_line: string
  city: string
  district: string
  ward: string
}

export interface OrderItem {
  product_name: string
  product_sku: string
  quantity: number
  unit_price: string
  subtotal: string
}
```

**`frontend/types/address.ts`**
```ts
export type AddressLabel = 'home' | 'office' | 'other'

export interface Address {
  id: string
  label: AddressLabel
  full_name: string
  phone: string
  address_line: string
  city: string
  district: string
  ward: string
  is_default: boolean
}
```

**`frontend/types/blog.ts`**
```ts
export interface BlogPost {
  id: string
  title: string
  slug: string
  excerpt: string
  content?: string
  featured_image: string
  author: { id: string; name: string }
  category: { id: number; name: string; slug: string }
  tags: BlogTag[]
  seo?: SeoMeta
  jsonld_schemas?: JsonldSchema[]
  published_at: string
  updated_at?: string
}

export interface BlogTag {
  id: number
  name: string
  slug: string
}

export interface BlogComment {
  id: number
  body: string
  user: { id: string; name: string }
  created_at: string
}
```

**`frontend/types/auth.ts`**
```ts
export interface AuthUser {
  id: string
  name: string
  email: string
  phone: string | null
  role: 'customer' | 'admin'
  email_verified_at: string | null
  created_at: string
}
```

**`frontend/utils/currency.ts`**
```ts
export const formatCurrency = (amount: string | number): string => {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(Number(amount))
}
```

**`frontend/utils/date.ts`**
```ts
export const formatDate = (iso: string): string => {
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(iso))
}
```

### Checklist
- [ ] All types compile — `npx vue-tsc --noEmit`
- [ ] `formatCurrency(1500000)` → `"1.500.000 ₫"`
- [ ] `formatDate("2026-04-07T10:00:00Z")` → `"07/04/2026"`

---

## P3 — API Layer

### Goal
Single `useApi.ts` composable that wraps `$fetch`, handles the envelope, and manages the guest cart session cookie.

### Files to create

**`frontend/composables/useApi.ts`**
```ts
export const useApi = () => {
  const config = useRuntimeConfig()

  const $api = $fetch.create({
    baseURL: config.public.apiBase,
    headers: { Accept: 'application/json' },
    onResponseError({ response }) {
      if (response.status === 401) navigateTo('/auth/login')
      if (response.status === 404) throw createError({ statusCode: 404, fatal: true })
      if (response.status === 500) throw createError({ statusCode: 500, fatal: true })
    },
  })

  return { $api }
}
```

**`frontend/composables/useCartSession.ts`** — guest session cookie
```ts
export const useCartSession = () => {
  const sessionId = useCookie('cart_session_id', {
    default: () => crypto.randomUUID(),
    maxAge: 60 * 60 * 24 * 7,
  })

  const cartHeaders = computed(() => ({
    'X-Session-ID': sessionId.value,
  }))

  return { sessionId, cartHeaders }
}
```

### Checklist
- [ ] `useApi().$api('/products')` returns data without CORS errors
- [ ] 401 response redirects to `/auth/login`
- [ ] 404 response triggers Nuxt error page
- [ ] `cart_session_id` cookie is set on first visit

---

## P4 — Layouts & Shell

### Goal
Create all three layouts and global navigation components.

### Files to create

**`frontend/layouts/default.vue`** — public pages
```vue
<template>
  <div>
    <AppHeader />
    <main>
      <slot />
    </main>
    <AppFooter />
    <CartDrawer />
  </div>
</template>
```

**`frontend/layouts/minimal.vue`** — auth pages (login, register)
```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-neutral-50">
    <slot />
  </div>
</template>
```

**`frontend/layouts/account.vue`** — account pages with sidebar
```vue
<template>
  <div>
    <AppHeader />
    <div class="container mx-auto flex gap-8 py-8">
      <AccountSidebar />
      <main class="flex-1">
        <slot />
      </main>
    </div>
    <AppFooter />
  </div>
</template>
```

**`frontend/components/App/AppHeader.vue`**
- Logo (NuxtLink to `/`)
- Category nav (fetch from `useCategory().getTree()`)
- SearchBar
- Cart icon with item count badge
- Auth: show name + account link if logged in, Login button if not

**`frontend/components/App/AppFooter.vue`**
- Company info, links, copyright

**`frontend/components/App/AppBreadcrumb.vue`**
- Accepts `items: { label: string; to?: string }[]` prop
- Renders with JSON-LD BreadcrumbList (passed to parent page for `JsonldRenderer`)

**`frontend/middleware/auth.ts`**
```ts
export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated } = useSanctumAuth()
  if (!isAuthenticated.value) return navigateTo('/auth/login')
})
```

**`frontend/middleware/guest.ts`**
```ts
export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated } = useSanctumAuth()
  if (isAuthenticated.value) return navigateTo('/')
})
```

**`frontend/public/robots.txt`**
```
User-agent: *
Allow: /
Disallow: /account/
Disallow: /cart
Disallow: /checkout
Sitemap: https://yourdomain.com/sitemap.xml
```

### Checklist
- [ ] Default layout renders header + footer on `/`
- [ ] Minimal layout renders centered box on `/auth/login`
- [ ] Account layout renders sidebar on `/account`
- [ ] `auth` middleware redirects unauthenticated to `/auth/login`
- [ ] `guest` middleware redirects authenticated to `/`

---

## P5 — Auth

### Goal
Login, register, Google OAuth, logout. Token stored via `nuxt-auth-sanctum` in httpOnly cookie.

### Composable

**`frontend/composables/useAuth.ts`**
```ts
export const useAuthActions = () => {
  const { $api } = useApi()
  const { sessionId } = useCartSession()

  const login = async (email: string, password: string) => {
    const res = await $api<ApiResponse<{ token: string; user: AuthUser }>>('/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    // nuxt-auth-sanctum handles token storage
    // merge guest cart after login
    await $api('/cart/merge', {
      method: 'POST',
      body: { session_id: sessionId.value },
    })
    return res.data
  }

  const register = async (name: string, email: string, password: string, passwordConfirmation: string) => {
    return $api('/auth/register', {
      method: 'POST',
      body: { name, email, password, password_confirmation: passwordConfirmation },
    })
  }

  const logout = async () => {
    await $api('/auth/logout', { method: 'POST' })
    navigateTo('/auth/login')
  }

  const googleAuth = async (idToken: string) => {
    return $api('/auth/google', { method: 'POST', body: { id_token: idToken } })
  }

  return { login, register, logout, googleAuth }
}
```

### Pages

**`frontend/pages/auth/login.vue`**
```ts
definePageMeta({ layout: 'minimal', middleware: 'guest' })
```
- Email + password form
- "Login with Google" button
- Error display from API 422 response
- Link to `/auth/register`

**`frontend/pages/auth/register.vue`**
```ts
definePageMeta({ layout: 'minimal', middleware: 'guest' })
```
- Name, email, password, confirm password
- Error display per field
- Link to `/auth/login`

**`frontend/pages/auth/google/callback.vue`**
```ts
definePageMeta({ layout: 'minimal' })
```
- On mount: extract `code` from URL, call `googleAuth()`, redirect to `/account`

### Checklist
- [ ] Login with valid credentials redirects to `/account`
- [ ] Login with wrong password shows field error from API
- [ ] Register creates account, redirects to `/account`
- [ ] Google OAuth callback stores token and redirects
- [ ] Guest cart is merged into auth cart after login
- [ ] Logout clears cookie and redirects to `/auth/login`

---

## P6 — Products & Categories

### Goal
SSR product listing, category filtering, and product detail pages with full SEO.

### Composables

**`frontend/composables/useProduct.ts`**
```ts
export const useProduct = () => {
  const { $api } = useApi()

  const getProducts = (params?: Record<string, string | number>) =>
    $api<ApiResponse<ProductListItem[]>>('/products', { params })

  const getProduct = (slug: string) =>
    $api<ApiResponse<ProductDetail>>(`/products/${slug}`)

  return { getProducts, getProduct }
}
```

**`frontend/composables/useCategory.ts`**
```ts
export const useCategory = () => {
  const { $api } = useApi()

  const getTree = () => $api<ApiResponse<Category[]>>('/categories')

  const getCategory = (slug: string, params?: Record<string, string | number>) =>
    $api<ApiResponse<Category & { products: ProductListItem[] }>>(`/categories/${slug}`, { params })

  return { getTree, getCategory }
}
```

### Pages

**`frontend/pages/index.vue`** — Homepage (SSR)
- Featured categories (`useCategory().getTree()`)
- Featured/latest products (`useProduct().getProducts({ sort: 'newest', per_page: 8 })`)
- `useSeo()` with homepage SEO meta
- `<JsonldRenderer>` with Organization + WebSite schemas

**`frontend/pages/products/[slug].vue`** — Product detail (SSR)
```ts
const { data } = await useAsyncData(`product-${slug}`, () => useProduct().getProduct(slug))
useSeo(data.value.seo)
```
- `<ProductDetail :product="data" />`
- `<JsonldRenderer :schemas="data.jsonld_schemas" />`

**`frontend/pages/categories/[slug].vue`** — Category + products (SSR)
```ts
const { data } = await useAsyncData(`category-${slug}`, () => useCategory().getCategory(slug, { page, sort, ... }))
useSeo(data.value.seo)
```
- `<CategoryTree>` sidebar
- `<ProductGrid>` with filters
- `<UiPagination>` component
- `<JsonldRenderer>` with BreadcrumbList schema

### Components

| Component | Props | Notes |
|---|---|---|
| `ProductCard.vue` | `product: ProductListItem` | Used in grid/list — show name, price, sale_price, thumbnail |
| `ProductGrid.vue` | `products: ProductListItem[]`, `loading: boolean` | Renders grid of ProductCard |
| `ProductDetail.vue` | `product: ProductDetail` | Full detail layout |
| `ProductImages.vue` | `images: ProductImage[]` | Gallery with zoom |
| `ProductVideo.vue` | `videos: ProductVideo[]` | Video player |
| `ProductPrice.vue` | `price: string`, `salePrice: string \| null` | Handles strikethrough logic |
| `CategoryTree.vue` | `categories: Category[]`, `activeSlug: string` | Nested sidebar nav |

### Checklist
- [ ] `GET /products` → SSR renders product grid
- [ ] `GET /products/{slug}` → SSR renders product detail with meta tags
- [ ] `GET /categories/{slug}` → SSR renders filtered product list
- [ ] View source shows `<title>`, `<meta>`, JSON-LD in HTML (not injected by JS)
- [ ] Filters (price, sort, in_stock) update URL query params
- [ ] Pagination works and updates `?page=`
- [ ] `<NuxtImg>` used for all images — no raw `<img>`

---

## P7 — Cart

### Goal
Guest + auth cart with persistent `CartDrawer`, add/remove/update quantity.

### Composable

**`frontend/composables/useCart.ts`**
```ts
export const useCart = () => {
  const { $api } = useApi()
  const { cartHeaders } = useCartSession()
  const cart = useState<Cart | null>('cart', () => null)

  const fetchCart = async () => {
    const res = await $api<ApiResponse<Cart>>('/cart', { headers: cartHeaders.value })
    cart.value = res.data
  }

  const addItem = async (productId: string, quantity = 1) => {
    const res = await $api<ApiResponse<Cart>>('/cart/items', {
      method: 'POST',
      headers: cartHeaders.value,
      body: { product_id: productId, quantity },
    })
    cart.value = res.data
  }

  const updateItem = async (itemId: number, quantity: number) => {
    const res = await $api<ApiResponse<Cart>>(`/cart/items/${itemId}`, {
      method: 'PUT',
      headers: cartHeaders.value,
      body: { quantity },
    })
    cart.value = res.data
  }

  const removeItem = async (itemId: number) => {
    await $api(`/cart/items/${itemId}`, { method: 'DELETE', headers: cartHeaders.value })
    await fetchCart()
  }

  const clearCart = async () => {
    await $api('/cart', { method: 'DELETE', headers: cartHeaders.value })
    cart.value = null
  }

  return { cart, fetchCart, addItem, updateItem, removeItem, clearCart }
}
```

### Pinia Store (if useState insufficient)

**`frontend/stores/cart.ts`**
```ts
export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([])
  const total = computed(() =>
    items.value.reduce((sum, i) => sum + i.quantity * parseFloat(i.product.sale_price ?? i.product.price), 0)
  )
  return { items, total }
})
```

### Components

| Component | Notes |
|---|---|
| `CartDrawer.vue` | Slide-out from right. Open/close via `useState('cartOpen')`. Lists CartItem components. Shows total. Checkout button. |
| `CartItem.vue` | Product thumbnail, name, price, quantity stepper, remove button |
| `CartSummary.vue` | Total, item count — used on /cart page and CartDrawer |
| `CartEmpty.vue` | Empty state with CTA to shop |

### Pages

**`frontend/pages/cart.vue`** (CSR)
- Full cart page — same logic as CartDrawer but full-width
- `useFetch` client-side (not `useAsyncData`)
- No SEO needed — `robots.txt` disallows `/cart`

### Checklist
- [ ] Guest can add items without login
- [ ] Cart persists across page refresh (cookie-based session)
- [ ] Quantity stepper updates cart in real-time
- [ ] Remove item works
- [ ] CartDrawer shows correct item count in AppHeader badge
- [ ] After login, guest cart merges into auth cart

---

## P8 — Checkout & Orders

### Goal
Checkout flow (address selection → place order) and order history/detail.

### Composable

**`frontend/composables/useOrder.ts`**
```ts
export const useOrder = () => {
  const { $api } = useApi()

  const placeOrder = (addressId: string, note?: string) =>
    $api<ApiResponse<Order>>('/orders', {
      method: 'POST',
      body: { address_id: addressId, note },
    })

  const getOrders = (params?: { page?: number; status?: string }) =>
    $api<ApiResponse<Order[]>>('/orders', { params })

  const getOrder = (id: string) =>
    $api<ApiResponse<Order>>(`/orders/${id}`)

  const cancelOrder = (id: string) =>
    $api<ApiResponse<{ id: string; status: string }>>(`/orders/${id}/cancel`, { method: 'PATCH' })

  return { placeOrder, getOrders, getOrder, cancelOrder }
}
```

### Pages

**`frontend/pages/checkout.vue`** (CSR, auth-protected)
```ts
definePageMeta({ middleware: 'auth' })
```
1. Show address list → user selects one or adds new
2. Show cart summary (read-only)
3. Optional note field
4. "Place Order" button → `placeOrder(addressId, note)`
5. On success: clear cart → redirect to `/account/orders/{id}`

**`frontend/pages/account/orders/index.vue`** (CSR, auth-protected)
- Paginated order list
- Status filter tabs (all / pending / delivered / cancelled)
- Each row: order ID, date, total, status badge, "View" link

**`frontend/pages/account/orders/[id].vue`** (CSR, auth-protected)
- Full order detail
- Items table, shipping address, totals
- "Cancel Order" button (visible only when `status = pending`)

### Components

| Component | Notes |
|---|---|
| `OrderCard.vue` | Used in order list — shows order ID, date, total, status badge |
| `OrderDetail.vue` | Full detail view with items table |
| `OrderList.vue` | Wrapper with filter tabs and pagination |

### Checklist
- [ ] Checkout page requires auth — redirect to login if not
- [ ] Address selection shows all saved addresses
- [ ] Place order clears cart and redirects to order detail
- [ ] Order list is paginated
- [ ] Cancel works only on pending orders
- [ ] Status badge color matches: pending=yellow, shipped=blue, delivered=green, cancelled=red

---

## P9 — Addresses

### Goal
CRUD address management in account area.

### Composable

**`frontend/composables/useAddress.ts`**
```ts
export const useAddress = () => {
  const { $api } = useApi()

  const getAddresses = () => $api<ApiResponse<Address[]>>('/addresses')
  const createAddress = (data: Omit<Address, 'id'>) =>
    $api<ApiResponse<Address>>('/addresses', { method: 'POST', body: data })
  const updateAddress = (id: string, data: Partial<Address>) =>
    $api<ApiResponse<Address>>(`/addresses/${id}`, { method: 'PUT', body: data })
  const deleteAddress = (id: string) =>
    $api(`/addresses/${id}`, { method: 'DELETE' })
  const setDefault = (id: string) =>
    $api<ApiResponse<Address>>(`/addresses/${id}/default`, { method: 'PATCH' })

  return { getAddresses, createAddress, updateAddress, deleteAddress, setDefault }
}
```

### Pages

**`frontend/pages/account/addresses/index.vue`** (CSR, auth-protected)
- List saved addresses
- "Add new" → show `AddressForm` inline or modal
- "Edit" → `AddressForm` pre-filled
- "Delete" with confirmation
- "Set as default" button

### Components

| Component | Props | Notes |
|---|---|---|
| `AddressList.vue` | `addresses: Address[]` | Card grid of AddressCard |
| `AddressCard.vue` | `address: Address` | Shows label, name, phone, address. Default badge. |
| `AddressForm.vue` | `address?: Address`, `onSave: fn` | Create/edit form. Uses `<UForm>` from Nuxt UI. |

### Checklist
- [ ] Address list loads on mount
- [ ] Create new address adds to list
- [ ] Edit updates in-place
- [ ] Delete removes with confirmation dialog
- [ ] Set default updates `is_default` badge instantly

---

## P10 — Blog

### Goal
SSR blog list, post detail, category filter, tag filter. Full SEO per post.

### Composable

**`frontend/composables/useBlog.ts`**
```ts
export const useBlog = () => {
  const { $api } = useApi()

  const getPosts = (params?: Record<string, string | number>) =>
    $api<ApiResponse<BlogPost[]>>('/blog', { params })

  const getPost = (slug: string) =>
    $api<ApiResponse<BlogPost>>(`/blog/${slug}`)

  const getCategories = () =>
    $api<ApiResponse<Category[]>>('/blog/categories')

  const getTags = () =>
    $api<ApiResponse<BlogTag[]>>('/blog/tags')

  const getComments = (slug: string, page = 1) =>
    $api<ApiResponse<BlogComment[]>>(`/blog/${slug}/comments`, { params: { page } })

  const postComment = (slug: string, body: string) =>
    $api(`/blog/${slug}/comments`, { method: 'POST', body: { body } })

  return { getPosts, getPost, getCategories, getTags, getComments, postComment }
}
```

### Pages

**`frontend/pages/blog/index.vue`** (SSR)
- Post grid, category filter sidebar, tag cloud
- Pagination
- `useSeo()` with blog index meta

**`frontend/pages/blog/[slug].vue`** (SSR)
```ts
const { data } = await useAsyncData(`blog-${slug}`, () => useBlog().getPost(slug))
useSeo(data.value.seo)
```
- Full post content via `<UiRichText :content="data.content" />`
- Author, date, category, tags
- `<JsonldRenderer :schemas="data.jsonld_schemas" />`
- Comments section

**`frontend/pages/blog/categories/[slug].vue`** (SSR)
- Filtered post list by blog category

### Components

| Component | Notes |
|---|---|
| `BlogCard.vue` | Featured image, title, excerpt, author, date, category tag |
| `BlogGrid.vue` | Grid of BlogCard |
| `BlogDetail.vue` | Full post layout with sidebar |
| `BlogComments.vue` | Comment list with pagination |
| `BlogCommentForm.vue` | Submit comment — requires auth |
| `UiRichText.vue` | Safely renders TinyMCE HTML via `v-html` — sanitize first |

### Checklist
- [ ] Blog list SSR — view source shows post titles and meta
- [ ] Blog detail SSR — view source shows full content, JSON-LD
- [ ] Category filter updates URL and re-fetches
- [ ] Tag filter works
- [ ] Comment form visible only to logged-in users
- [ ] Comment submit shows "pending approval" message

---

## P11 — Search

### Goal
Full-text search across products and blog with debounce. CSR (no SSR needed).

### Composable

**`frontend/composables/useSearch.ts`**
```ts
export const useSearch = () => {
  const { $api } = useApi()
  const query = ref('')
  const results = ref<{ products: ProductListItem[]; blog: BlogPost[] } | null>(null)
  const loading = ref(false)

  let debounceTimer: ReturnType<typeof setTimeout>

  const search = (q: string, type: 'all' | 'products' | 'blog' = 'all') => {
    query.value = q
    clearTimeout(debounceTimer)
    if (!q.trim()) { results.value = null; return }
    loading.value = true
    debounceTimer = setTimeout(async () => {
      const res = await $api<ApiResponse<typeof results.value>>('/search', { params: { q, type } })
      results.value = res.data
      loading.value = false
    }, 300)
  }

  return { query, results, loading, search }
}
```

### Pages & Components

**`frontend/pages/search.vue`** (CSR)
- `SearchBar` with live debounce
- Results split by products / blog
- `SearchEmpty.vue` when no results

**`frontend/components/Search/SearchBar.vue`**
- `<input>` with debounce — calls `useSearch().search()`
- Accessible: `role="search"`, `aria-label`

**`frontend/components/Search/SearchResults.vue`**
- Two sections: Products and Blog
- Renders `ProductCard` and `BlogCard` mini variants

### Checklist
- [ ] Typing triggers search after 300ms debounce
- [ ] Empty query clears results
- [ ] Loading spinner shows during fetch
- [ ] Results link to correct product/blog pages
- [ ] No duplicate requests when typing fast

---

## P12 — Account

### Goal
Profile overview with account layout and sidebar.

### Pages

**`frontend/pages/account/index.vue`** (CSR, auth-protected)
```ts
definePageMeta({ layout: 'account', middleware: 'auth' })
```
- Display user name, email, phone
- "Edit profile" form (PUT `/auth/me`)

**`frontend/components/App/AccountSidebar.vue`**
Links:
- Profile → `/account`
- Orders → `/account/orders`
- Addresses → `/account/addresses`
- Logout button → calls `useAuthActions().logout()`

### Checklist
- [ ] Account pages redirect to login if not authenticated
- [ ] Profile update form works
- [ ] Sidebar highlights active route

---

## P13 — SEO Layer

### Goal
Reusable SEO composable and JSON-LD renderer — applied on ALL SSR pages.

### Files

**`frontend/composables/useSeo.ts`**
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

**`frontend/components/Seo/JsonldRenderer.vue`**
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

### SSR pages that MUST have both `useSeo()` and `<JsonldRenderer>`
- [ ] `pages/index.vue` (homepage)
- [ ] `pages/products/[slug].vue`
- [ ] `pages/categories/[slug].vue`
- [ ] `pages/blog/index.vue`
- [ ] `pages/blog/[slug].vue`
- [ ] `pages/blog/categories/[slug].vue`

### Verification method
For each SSR page — view source in browser and confirm:
```html
<title>Page Title | YourShop</title>
<meta name="description" content="...">
<meta property="og:image" content="...">
<link rel="canonical" href="...">
<script type="application/ld+json">{"@context":"https://schema.org",...}</script>
```
All of the above must be present **in the raw HTML** — not injected by JavaScript after page load.

---

## P14 — Performance

### Goal
Pass Core Web Vitals targets before launch.

| Metric | Target |
|---|---|
| LCP | < 2.5s |
| INP | < 100ms |
| CLS | < 0.1 |

### Checklist
- [ ] Every `<NuxtImg>` has explicit `width` + `height` — prevents CLS
- [ ] Product thumbnail uses `format="webp"` + `loading="lazy"`
- [ ] Above-fold images use `loading="eager"` (hero image on homepage, product detail)
- [ ] `sizes` attribute set on `<NuxtImg>` — responsive srcset
- [ ] Fonts load with `font-display: swap`
- [ ] Run Lighthouse audit on: `/`, `/products/{slug}`, `/blog/{slug}`
- [ ] All three pages score ≥ 90 Performance on Lighthouse
- [ ] Run PageSpeed Insights on deployed URL before launch

---

## Dependency Order (What blocks what)

```
P1 Setup
  └── P2 Types & Utils
        └── P3 API Layer
              ├── P4 Layouts & Shell
              │     └── P5 Auth
              │           ├── P6 Products & Categories ← P13 SEO
              │           ├── P7 Cart
              │           │     └── P8 Checkout & Orders
              │           │           └── P9 Addresses
              │           ├── P10 Blog ← P13 SEO
              │           └── P11 Search
              └── P12 Account (depends on P5)

P13 SEO — applied during P6 and P10, verified after
P14 Performance — done last, after all pages exist
```

---

## Commit Convention

```
feat(frontend): init Nuxt 3 project with all packages
feat(frontend): add TypeScript types for API envelope
feat(frontend): add useApi composable with envelope handling
feat(frontend): add auth login and register pages
feat(frontend): add product SSR pages with SEO
feat(frontend): add cart composable and CartDrawer
feat(frontend): add checkout and order pages
feat(frontend): add blog SSR pages with JSON-LD
feat(frontend): add search with debounce
seo(frontend): apply useSeo and JsonldRenderer to all SSR pages
perf(frontend): audit and fix Core Web Vitals
```

---

*Last updated: April 2026. Update this file if new pages or composables are added.*
