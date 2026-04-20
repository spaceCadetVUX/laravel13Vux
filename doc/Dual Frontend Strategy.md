# Dual Frontend Strategy
> Chiến lược 2 hướng frontend trên cùng 1 Laravel backend
> Created: April 2026

---

## Tổng quan

Dự án này hỗ trợ **2 hướng frontend** phục vụ 2 phân khúc khách hàng khác nhau, chia sẻ hoàn toàn backend Laravel 13.

```
                    ┌─────────────────────────────────┐
                    │        Laravel 13 Backend        │
                    │                                  │
                    │  Models ─ Services ─ Repositories│  SHARED
                    │  Filament Admin                  │  SHARED
                    │  SEO / GEO / JSON-LD / LLMs      │  SHARED
                    │  Queue / Redis / Meilisearch      │  SHARED
                    │  Observers / Jobs / Events        │  SHARED
                    └──────────┬──────────────┬────────┘
                               │              │
                    ┌──────────▼───┐     ┌────▼──────────┐
                    │  routes/api  │     │  routes/web   │
                    │  JSON resp.  │     │  Blade views  │
                    └──────────┬───┘     └────┬──────────┘
                               │              │
                    ┌──────────▼───┐     ┌────▼──────────┐
                    │   Nuxt 3     │     │  Blade + BS5  │
                    │  (Premium)   │     │   (Budget)    │
                    └──────────────┘     └───────────────┘
```

---

## Business Model — 3 Tier

| Gói | Stack Frontend | Phù hợp | Đặc điểm |
|---|---|---|---|
| **Starter** | Blade + Bootstrap 5 | SME, cần nhanh | Server-side render, 1 server |
| **Pro** | Blade + Livewire | Tương tác tốt hơn | Reactive UI, vẫn 1 server |
| **Premium** | Nuxt 3 + API | Scale lớn, app-like UX | Tách biệt hoàn toàn, SSR/ISR |

> **Lợi thế:** Build backend 1 lần — bán được 3 tier sản phẩm.

---

## Layer nào SHARED — không viết lại

| Layer | Ghi chú |
|---|---|
| `app/Models/` | 100% shared |
| `app/Services/` | 100% shared |
| `app/Repositories/` | 100% shared |
| `app/Http/Requests/` | 100% shared |
| `app/Observers/` | 100% shared |
| `app/Jobs/` | 100% shared |
| `app/Filament/` | 100% shared — cùng 1 admin panel |
| SEO infrastructure | `seo_meta`, `jsonld_schemas`, `sitemap_entries`, `llms_entries` — shared |
| `app/Enums/` | 100% shared |
| Queue / Redis / Horizon | 100% shared |
| Meilisearch / Scout | 100% shared |

---

## Layer nào KHÁC NHAU

| | API + Nuxt (Premium) | Blade (Budget) |
|---|---|---|
| Routes | `routes/api.php` | `routes/web.php` |
| Controllers | `app/Http/Controllers/Api/V1/` | `app/Http/Controllers/Web/` |
| Auth | Sanctum token (stateless) | Session — web guard (stateful) |
| Response format | `ApiResource` JSON | `view('...')` Blade |
| SEO rendering | Nuxt `useSeo()` + `<JsonldRenderer>` | Blade `@section('meta')` + `@json($schemas)` |
| Frontend location | `/frontend` (separate repo/folder) | `resources/views/` |
| Hosting | API server + Node SSR server | 1 server duy nhất |
| Build complexity | Cao hơn | Thấp hơn |

---

## Cấu trúc thư mục — Blade tier

```
app/Http/Controllers/Web/
├── HomeController.php
├── ProductController.php
├── CategoryController.php
├── BlogController.php
├── CartController.php
├── OrderController.php
├── SearchController.php
└── Auth/
    ├── LoginController.php
    ├── RegisterController.php
    └── ProfileController.php

resources/views/
├── layouts/
│   ├── app.blade.php          ← main layout (header, footer, meta)
│   └── guest.blade.php        ← auth pages layout
├── components/
│   ├── product-card.blade.php
│   ├── pagination.blade.php
│   └── seo-meta.blade.php     ← render seo_meta fields
├── products/
│   ├── index.blade.php
│   └── show.blade.php
├── categories/
│   └── show.blade.php
├── blog/
│   ├── index.blade.php
│   └── show.blade.php
├── cart/
│   └── index.blade.php
├── checkout/
│   └── index.blade.php
├── orders/
│   ├── index.blade.php
│   └── show.blade.php
└── auth/
    ├── login.blade.php
    └── register.blade.php
```

---

## Pattern — Blade controller tái dụng Service

```php
// app/Http/Controllers/Web/ProductController.php
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request)
    {
        $products = $this->productService->list($request->all());
        return view('products.index', compact('products'));
    }

    public function show(string $slug)
    {
        $product = $this->productService->getBySlug($slug);
        $seo     = $product->seoMeta;       // ← SEO tự động từ seo_meta
        $schemas = $product->activeSchemas; // ← JSON-LD tự động

        return view('products.show', compact('product', 'seo', 'schemas'));
    }
}
```

---

## SEO — Blade render

```blade
{{-- resources/views/layouts/app.blade.php --}}
<head>
    <title>{{ $seo->meta_title ?? config('app.name') }}</title>
    <meta name="description" content="{{ $seo->meta_description }}">
    <meta name="robots" content="{{ $seo->robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $seo->canonical_url ?? url()->current() }}">

    {{-- Open Graph --}}
    <meta property="og:title"       content="{{ $seo->og_title ?? $seo->meta_title }}">
    <meta property="og:description" content="{{ $seo->og_description }}">
    <meta property="og:image"       content="{{ $seo->og_image }}">
    <meta property="og:type"        content="{{ $seo->og_type ?? 'website' }}">

    {{-- JSON-LD --}}
    @foreach($schemas ?? [] as $schema)
        @if($schema->is_active)
            <script type="application/ld+json">
                {!! json_encode($schema->payload, JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endif
    @endforeach
</head>
```

---

## Auth — Blade dùng Session guard

```php
// config/auth.php — thêm web guard cho Blade
'guards' => [
    'web'     => ['driver' => 'session',  'provider' => 'users'], // Blade
    'sanctum' => ['driver' => 'sanctum',  'provider' => 'users'], // API
],
```

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/account', [ProfileController::class, 'show']);
});

Route::middleware('guest')->group(function () {
    Route::get('/login',    [LoginController::class, 'showForm']);
    Route::post('/login',   [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'showForm']);
    Route::post('/register',[RegisterController::class, 'register']);
});
```

---

## Timeline ước tính — Thêm Blade tier

| Tuần | Việc |
|---|---|
| 1 | Layout, auth (login/register/profile), middleware |
| 2 | Product listing, product detail, category pages |
| 3 | Cart, checkout, order history |
| 4 | Blog, search, sitemap.xml route |
| 5 | Polish, responsive, performance test |

> Nhanh hơn Nuxt vì không cần lo SSR config, hydration, composables, API state management.

---

## SEO — So sánh 2 hướng

| | Blade | Nuxt 3 |
|---|---|---|
| HTML render | Server-side native | SSR (Node.js) |
| TTFB | Nhanh | Nhanh (nếu cấu hình đúng) |
| Cấu hình SEO | Đơn giản | Phức tạp hơn (useSeo, hydration) |
| PageSpeed | Cao nếu optimize CSS/JS | Cao nếu cấu hình đúng |
| Rủi ro SEO | Thấp | Cao hơn nếu misconfigured |
| Google cache | Tốt | Tốt |

> **Kết luận SEO:** Blade an toàn hơn, Nuxt mạnh hơn nếu làm đúng.

---

## Quyết định chưa thực hiện

- [ ] Tạo `routes/web.php` với các route Blade
- [ ] Tạo `app/Http/Controllers/Web/` controllers
- [ ] Tạo `resources/views/layouts/app.blade.php`
- [ ] Cấu hình session auth cho web guard
- [ ] Quyết định CSS framework cho Blade tier (Bootstrap 5 / Tailwind)
- [ ] Quyết định có dùng Livewire cho Pro tier không

---

*Tài liệu này là chiến lược kiến trúc — chưa implement. Xem `doc/Frontend Architecture — Nuxt 3 Storefront.md` cho plan Nuxt 3.*
