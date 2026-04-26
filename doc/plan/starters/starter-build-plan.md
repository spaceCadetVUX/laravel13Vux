# Starter Tier — Build Plan (Blade + Bootstrap 5)
**Last Updated:** 2026-04-26
**Stack:** Laravel 13 Blade + Bootstrap 5
**Backend:** 100% shared — Services, Models, Observers, SEO pipeline không viết lại
**Auth:** Session guard (web) — tách biệt hoàn toàn với Sanctum token của API

---

## Tổng quan Sprint

| Sprint | Tên | Deliverable |
|---|---|---|
| S01 | Foundation | Layout, routing, session auth |
| S02 | Catalog | Home, category, product listing & detail, search |
| S03 | Blog | Blog listing, detail, category |
| S04 | Commerce | Cart, checkout, order placement |
| S05 | Account & Polish | Profile, address, order history, performance |

---

## Quy tắc bắt buộc (Blade tier)

- Controllers tại `app/Http/Controllers/Web/` — không dùng chung với API controllers
- Controllers chỉ gọi Service — không có Eloquent query trực tiếp
- Blade views tại `resources/views/` — layout + components + pages
- SEO: render từ `$seo` (SeoMeta model) + `$schemas` (JsonldSchema collection)
- Auth: `auth` middleware → web guard (session), không phải sanctum
- Pagination: dùng Bootstrap 5 (`AppServiceProvider::boot()` → `Paginator::useBootstrapFive()`)

---

## S01 — Foundation
**Goal:** Layout chạy được, auth hoạt động, routing đầy đủ

### Bước 1 — Cấu hình auth web guard

**`config/auth.php`** — đảm bảo web guard dùng session:
```php
'guards' => [
    'web' => [
        'driver'   => 'session',
        'provider' => 'users',
    ],
    // ... sanctum guard giữ nguyên
],
```

### Bước 2 — AppServiceProvider

Thêm vào `boot()`:
```php
use Illuminate\Pagination\Paginator;

Paginator::useBootstrapFive();
```

### Bước 3 — routes/web.php

Thêm Blade routes (bên dưới sitemap/llms routes hiện có):

```php
// ── Public ────────────────────────────────────────────────────────────────────
Route::get('/',                [Web\HomeController::class,        'index'])->name('home');
Route::get('/products',        [Web\ProductController::class,     'index'])->name('products.index');
Route::get('/products/{slug}', [Web\ProductController::class,     'show'])->name('products.show');
Route::get('/categories/{slug}',[Web\CategoryController::class,   'show'])->name('categories.show');
Route::get('/search',          [Web\SearchController::class,      'index'])->name('search');
Route::get('/blog',            [Web\BlogController::class,        'index'])->name('blog.index');
Route::get('/blog/{slug}',     [Web\BlogController::class,        'show'])->name('blog.show');
Route::get('/blog/category/{slug}', [Web\BlogController::class,   'category'])->name('blog.category');

// ── Guest only ────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [Web\Auth\LoginController::class,    'showForm'])->name('login');
    Route::post('/login',    [Web\Auth\LoginController::class,    'login']);
    Route::get('/register',  [Web\Auth\RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [Web\Auth\RegisterController::class, 'register']);
    Route::get('/auth/google',          [Web\Auth\SocialController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [Web\Auth\SocialController::class, 'callback']);
});

// ── Auth required ─────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout',           [Web\Auth\LoginController::class,   'logout'])->name('logout');
    Route::get('/account',           [Web\AccountController::class,      'show'])->name('account');
    Route::put('/account',           [Web\AccountController::class,      'update']);
    Route::get('/addresses',         [Web\AddressController::class,      'index'])->name('addresses.index');
    Route::post('/addresses',        [Web\AddressController::class,      'store']);
    Route::put('/addresses/{id}',    [Web\AddressController::class,      'update']);
    Route::delete('/addresses/{id}', [Web\AddressController::class,      'destroy']);
    Route::get('/cart',              [Web\CartController::class,         'index'])->name('cart.index');
    Route::post('/cart/items',       [Web\CartController::class,         'addItem']);
    Route::put('/cart/items/{id}',   [Web\CartController::class,         'updateItem']);
    Route::delete('/cart/items/{id}',[Web\CartController::class,         'removeItem']);
    Route::get('/checkout',          [Web\CheckoutController::class,     'index'])->name('checkout');
    Route::post('/checkout',         [Web\CheckoutController::class,     'place']);
    Route::get('/orders',            [Web\OrderController::class,        'index'])->name('orders.index');
    Route::get('/orders/{id}',       [Web\OrderController::class,        'show'])->name('orders.show');
});
```

### Bước 4 — Layout chính

**`resources/views/layouts/app.blade.php`**

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->meta_title ?? config('app.name') }}</title>

    {{-- SEO Meta --}}
    @isset($seo)
        <meta name="description" content="{{ $seo->meta_description }}">
        <meta name="robots"      content="{{ $seo->robots ?? 'index, follow' }}">
        <link rel="canonical"    href="{{ $seo->canonical_url ?? url()->current() }}">
        <meta property="og:title"       content="{{ $seo->og_title ?? $seo->meta_title }}">
        <meta property="og:description" content="{{ $seo->og_description ?? $seo->meta_description }}">
        <meta property="og:image"       content="{{ $seo->og_image }}">
        <meta property="og:type"        content="{{ $seo->og_type ?? 'website' }}">
        <meta property="og:url"         content="{{ $seo->canonical_url ?? url()->current() }}">
    @endisset

    {{-- JSON-LD --}}
    @isset($schemas)
        @foreach($schemas as $schema)
            <script type="application/ld+json">{!! json_encode($schema->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endforeach
    @endisset

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
    @include('components.navbar')

    <main>
        @yield('content')
    </main>

    @include('components.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
```

**`resources/views/layouts/guest.blade.php`** — layout đơn giản cho login/register (không có navbar).

### Bước 5 — Controllers cần tạo S01

```
app/Http/Controllers/Web/
├── Auth/
│   ├── LoginController.php      ← showForm, login, logout — gọi AuthService
│   ├── RegisterController.php   ← showForm, register — gọi AuthService
│   └── SocialController.php     ← redirect, callback — gọi SocialAuthService
├── HomeController.php           ← index
```

**Pattern chuẩn:**
```php
class LoginController extends Controller
{
    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Sai thông tin đăng nhập.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
```

### Bước 6 — Views cần tạo S01

```
resources/views/
├── layouts/
│   ├── app.blade.php
│   └── guest.blade.php
├── components/
│   ├── navbar.blade.php
│   └── footer.blade.php
├── auth/
│   ├── login.blade.php
│   └── register.blade.php
└── home/
    └── index.blade.php          ← placeholder, điền S02
```

---

## S02 — Catalog
**Goal:** Home page, category tree, product listing & detail, search

### Controllers

```
app/Http/Controllers/Web/
├── HomeController.php           ← load featured products, categories
├── CategoryController.php       ← show($slug) — products in category
├── ProductController.php        ← index (listing), show($slug)
└── SearchController.php         ← index — Meilisearch via ProductService
```

**HomeController pattern:**
```php
public function index(): View
{
    $categories = $this->categoryService->tree();
    $featured   = $this->productService->featured(limit: 8);
    return view('home.index', compact('categories', 'featured'));
}
```

**ProductController::show pattern:**
```php
public function show(string $slug): View
{
    $product = $this->productService->getBySlug($slug); // throws 404 if not found
    $seo     = $product->seoMeta;
    $schemas = $product->activeSchemas;
    return view('products.show', compact('product', 'seo', 'schemas'));
}
```

### Views

```
resources/views/
├── home/
│   └── index.blade.php          ← hero, featured products, category grid
├── products/
│   ├── index.blade.php          ← grid listing + Bootstrap 5 pagination
│   ├── show.blade.php           ← detail: images, price, description, breadcrumb
│   └── _card.blade.php          ← product card component (reusable)
├── categories/
│   └── show.blade.php           ← category header + product grid
└── search/
    └── index.blade.php          ← search bar + results
```

### SEO cần chú ý S02

- `products.show` → pass `$seo` + `$schemas` vào layout ✅
- `categories.show` → pass `$seo` + `$schemas` vào layout ✅
- `products.index` → không có seo_meta riêng → dùng default từ `config('seo')`
- `search` → `<meta name="robots" content="noindex">` — trang search không nên index

---

## S03 — Blog
**Goal:** Blog listing, blog detail, blog category page

### Controllers

```
app/Http/Controllers/Web/
└── BlogController.php
    ├── index()        ← danh sách bài viết published
    ├── show($slug)    ← bài viết detail
    └── category($slug) ← bài viết theo blog category
```

### Views

```
resources/views/
└── blog/
    ├── index.blade.php          ← listing, pagination
    ├── show.blade.php           ← full post, author, tags, breadcrumb
    ├── category.blade.php       ← filtered listing by category
    └── _card.blade.php          ← blog card component
```

### SEO cần chú ý S03

- `blog.show` → pass `$seo` (Article meta) + `$schemas` (Article + BreadcrumbList JSON-LD)
- `blog.category` → pass `$seo` + `$schemas` (CollectionPage + BreadcrumbList)
- `blog.index` → default SEO từ config

---

## S04 — Commerce
**Goal:** Cart hoạt động, checkout đặt được hàng

### Controllers

```
app/Http/Controllers/Web/
├── CartController.php
│   ├── index()         ← hiển thị cart
│   ├── addItem()       ← POST, gọi CartService::add()
│   ├── updateItem($id) ← PUT, gọi CartService::update()
│   └── removeItem($id) ← DELETE, gọi CartService::remove()
├── CheckoutController.php
│   ├── index()         ← form checkout (địa chỉ, summary)
│   └── place()         ← POST, gọi OrderService::place()
└── OrderController.php
    ├── index()         ← danh sách orders của user
    └── show($id)       ← order detail
```

### Views

```
resources/views/
├── cart/
│   └── index.blade.php          ← cart items table, quantity update, total
├── checkout/
│   └── index.blade.php          ← address picker, order summary, place button
└── orders/
    ├── index.blade.php          ← order history list
    └── show.blade.php           ← order detail + items
```

### Lưu ý S04

- Cart dùng `CartService` hiện có — không viết lại logic
- `CartController` dùng `Auth::user()->cart` — web guard auth
- Sau khi place order → redirect đến `orders.show` với flash message
- Checkout form validate địa chỉ client-side (Bootstrap validation) + server-side (FormRequest)
- SEO: cart/checkout/orders → `<meta name="robots" content="noindex, nofollow">`

---

## S05 — Account & Polish
**Goal:** Profile, addresses, responsive hoàn thiện, performance

### Controllers

```
app/Http/Controllers/Web/
├── AccountController.php
│   ├── show()    ← profile page
│   └── update()  ← PUT, cập nhật name
└── AddressController.php
    ├── index()       ← danh sách địa chỉ
    ├── store()       ← POST
    ├── update($id)   ← PUT
    └── destroy($id)  ← DELETE
```

### Views

```
resources/views/
└── account/
    ├── show.blade.php           ← profile info + change password link
    └── addresses.blade.php      ← address list + inline add/edit form
```

### Performance — việc cần làm S05

1. **Response cache** — thêm middleware `responsecache` vào public routes (home, products, blog):
```php
Route::middleware('responsecache')->group(function () {
    Route::get('/', ...);
    Route::get('/products', ...);
    Route::get('/products/{slug}', ...);
    Route::get('/blog', ...);
    Route::get('/blog/{slug}', ...);
});
```

2. **Production caching:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Lazy load images** — thêm `loading="lazy"` trên tất cả `<img>` trừ above-the-fold

4. **Bootstrap 5 CDN** → đổi sang local compile (Vite + npm) nếu cần tối ưu

---

## Checklist trước khi ship

```
□ Tất cả public pages render đúng meta title + description
□ Tất cả public pages có canonical URL
□ Product detail + Blog detail có JSON-LD trong <head>
□ Cart / Checkout / Account có robots: noindex
□ Auth middleware hoạt động đúng (auth redirect → /login)
□ Guest middleware hoạt động đúng (đã login → redirect về home)
□ Google OAuth callback hoạt động
□ Pagination dùng Bootstrap 5 style
□ Responsive trên mobile (375px) và tablet (768px)
□ php artisan test passes
□ ./vendor/bin/pint passes
```

---

## File locations tóm tắt

```
app/Http/Controllers/Web/       ← tất cả Blade controllers
resources/views/layouts/        ← app.blade.php, guest.blade.php
resources/views/components/     ← navbar, footer, _card components
resources/views/{domain}/       ← pages theo domain
routes/web.php                  ← thêm Blade routes (giữ sitemap/llms routes)
config/auth.php                 ← verify web guard
```
