# Multilingual Architecture Plan
> **Project:** Backbone — B2C E-commerce + Blog
> **Stack:** Laravel 13 + Blade (no Nuxt)
> **Locales:** `vi` (default) + `en` (extensible to any locale)
> **Frontend:** Blade only — SSR natively, zero hydration cost
> **Last Updated:** April 2026 (rev 2 — confirmed decisions applied)

---

## Table of Contents
1. [Quyết định kiến trúc](#1-quyết-định-kiến-trúc)
2. [URL Structure](#2-url-structure)
3. [Database Schema — Translation Tables](#3-database-schema--translation-tables)
4. [Locale Detection & Routing](#4-locale-detection--routing)
5. [SEO Layer — Multilingual](#5-seo-layer--multilingual)
6. [GEO Layer — Multilingual](#6-geo-layer--multilingual)
7. [JSON-LD — Multilingual](#7-json-ld--multilingual)
8. [LLMs / AI Discoverability](#8-llms--ai-discoverability)
9. [Sitemap — Multilingual](#9-sitemap--multilingual)
10. [Redirects — Multilingual](#10-redirects--multilingual)
11. [Admin — Filament Multilingual Input](#11-admin--filament-multilingual-input)
12. [Blade Views Strategy](#12-blade-views-strategy)
13. [Sprint Build Order](#13-sprint-build-order)
14. [Packages](#14-packages)
15. [Checklist sebelum tiếp tục](#15-checklist-trước-khi-build)

---

## 1. Quyết định kiến trúc

### Tại sao Blade thay vì Nuxt?
| Tiêu chí | Blade + Laravel | Nuxt 3 + API |
|---|---|---|
| SSR | Native — không cần hydration | Cần SSR mode, phức tạp hơn |
| SEO | URL → Blade render → full HTML | Cần `useAsyncData` + đồng bộ locale |
| Locale routing | `SetLocale` middleware duy nhất | Nuxt i18n + API header + Sanctum |
| Multilingual slug | Đơn giản, resolve từ DB | Phức tạp hơn vì split giữa API và Nuxt |
| Thời gian build | Nhanh hơn ~40% | Chậm hơn |
| Caching | `spatie/responsecache` + Redis | Nuxt cache + Laravel cache 2 tầng |

**Kết luận:** Blade là lựa chọn đúng cho dự án này.

### Locale default
- **`vi` là default** — hầu hết user là người Việt, Google Việt Nam index chính
- Không có prefix cho vi: `site.com/products/ao-thun` → không dùng, xem bên dưới
- **Có prefix cho tất cả locale** — kể cả `vi` → đơn giản hóa logic, không có edge case

```
/vi/products/ao-thun-samsung   ← tiếng Việt
/en/products/samsung-t-shirt   ← tiếng Anh
```

> Nếu user vào `site.com/` → redirect 302 về `/vi/` (detect từ `Accept-Language`)
> Nếu user vào `site.com/products/abc` (không có locale) → redirect 301 về `/vi/products/abc`

---

## 2. URL Structure

### Công thức
```
/{locale}/{segment}
```

### Ví dụ đầy đủ
```
# Trang chính
/vi/
/en/

# Danh mục
/vi/categories/ao-thun
/en/categories/t-shirts

# Sản phẩm
/vi/products/ao-thun-uniqlo-size-m
/en/products/uniqlo-t-shirt-size-m

# Blog
/vi/blog/cach-phoi-do-mua-he
/en/blog/how-to-style-summer-outfits

# Blog category
/vi/blog/categories/thoi-trang
/en/blog/categories/fashion

# Tìm kiếm
/vi/search?q=ao+thun
/en/search?q=t+shirt

# Trang tĩnh (không cần dịch slug)
/vi/about
/en/about
/vi/contact
/en/contact
```

### Canonical — mỗi trang chỉ có 1, tự trỏ về chính nó
```html
<!-- /vi/products/ao-thun-uniqlo -->
<link rel="canonical" href="https://site.com/vi/products/ao-thun-uniqlo" />

<!-- /en/products/uniqlo-t-shirt -->
<link rel="canonical" href="https://site.com/en/products/uniqlo-t-shirt" />
```

> **Quan trọng:** Canonical KHÔNG phải "chọn 1 locale làm canonical cho cả site".
> Mỗi URL tự trỏ canonical về chính mình → Google biết đây là 2 trang riêng biệt, hợp lệ, không duplicate.
> Hreflang mới là thứ nói cho Google biết "2 trang này là alternate của nhau theo ngôn ngữ".
> `x-default` (hreflang) trỏ về `vi` — chỉ ảnh hưởng khi Google không match được locale user.

### Hreflang — bắt buộc trên mọi trang, xuất hiện trên CẢ HAI phiên bản
```html
<!-- Xuất hiện trên /vi/products/ao-thun-uniqlo VÀ /en/products/uniqlo-t-shirt -->
<link rel="alternate" hreflang="vi" href="https://site.com/vi/products/ao-thun-uniqlo" />
<link rel="alternate" hreflang="en" href="https://site.com/en/products/uniqlo-t-shirt" />
<link rel="alternate" hreflang="x-default" href="https://site.com/vi/products/ao-thun-uniqlo" />
```

---

## 3. Database Schema — Translation Tables

### Nguyên tắc thiết kế
- Table gốc (`products`, `categories`, v.v.) giữ nguyên — chứa dữ liệu **không phụ thuộc ngôn ngữ** (price, stock, SKU, sort_order)
- Mỗi model có 1 `_translations` table riêng — chứa `name`, `slug`, `description`, v.v. theo `locale`
- **Không** dùng JSON column cho translations — khó query, khó index, khó validate từng locale

### Image & Video — dùng chung across locales
- `product_images` và `product_videos` **không có** `locale` column — file path là universal
- `alt_text` trong `product_images` cần dịch per locale vì ảnh hưởng SEO image search
- **Giải pháp:** Thêm `product_image_alt_translations` hoặc đơn giản hơn: derive alt_text từ `product_translations.name` trong Blade view (không lưu DB riêng)
- **Quyết định chọn:** Derive trong view — `alt="{{ $product->translation($locale)->name }}"` — đủ SEO, không thêm bảng

```
product_images
├── id
├── product_id
├── path          ← dùng chung (vi lẫn en hiển thị cùng ảnh)
├── alt_text      ← giữ làm fallback (vi), Blade sẽ override bằng translation
├── sort_order
└── timestamps
```

### `product_translations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `product_id` | uuid | FK → products.id, CASCADE | |
| `locale` | varchar(10) | NOT NULL | `vi`, `en`, ... |
| `name` | varchar(500) | NOT NULL | |
| `slug` | varchar(600) | NOT NULL | Index: unique(locale, slug) |
| `short_description` | text | nullable | |
| `description` | longtext | nullable | TinyMCE rich text |
| `price` | decimal(12,2) | nullable | Admin nhập riêng per locale. Null → fallback về `products.price` |
| `currency` | varchar(10) | nullable | `VND`, `USD`, v.v. Null → fallback về currency mặc định |
| `meta_title` | varchar(255) | nullable | Override SEO title per locale |
| `meta_description` | varchar(500) | nullable | Override SEO desc per locale |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Index:** `UNIQUE(locale, slug)`, `INDEX(product_id, locale)`
>
> **Price fallback logic:** `$translation->price ?? $product->price` — nếu admin không nhập giá cho locale en thì lấy giá gốc từ `products.price`. Currency tương tự: `$translation->currency ?? config('app.default_currency')`.
>
> **JSON-LD:** `priceCurrency` lấy từ `$translation->currency`, không qua CurrencyService auto-convert.

---

### `category_translations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK | |
| `category_id` | bigint | FK → categories.id, CASCADE | |
| `locale` | varchar(10) | NOT NULL | |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(300) | NOT NULL | |
| `description` | text | nullable | |
| `meta_title` | varchar(255) | nullable | |
| `meta_description` | varchar(500) | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Index:** `UNIQUE(locale, slug)`, `INDEX(category_id, locale)`

---

### `blog_post_translations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK | |
| `blog_post_id` | uuid | FK → blog_posts.id, CASCADE | |
| `locale` | varchar(10) | NOT NULL | |
| `title` | varchar(500) | NOT NULL | |
| `slug` | varchar(600) | NOT NULL | |
| `excerpt` | text | nullable | |
| `body` | longtext | nullable | |
| `meta_title` | varchar(255) | nullable | |
| `meta_description` | varchar(500) | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Index:** `UNIQUE(locale, slug)`, `INDEX(blog_post_id, locale)`

---

### `blog_category_translations`
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK | |
| `blog_category_id` | bigint | FK → blog_categories.id, CASCADE | |
| `locale` | varchar(10) | NOT NULL | |
| `name` | varchar(255) | NOT NULL | |
| `slug` | varchar(300) | NOT NULL | |
| `description` | text | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

---

### `page_translations` *(trang tĩnh — About, Contact, v.v.)*
| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | bigint | PK | |
| `page_key` | varchar(100) | NOT NULL | `about`, `contact`, `faq` |
| `locale` | varchar(10) | NOT NULL | |
| `title` | varchar(255) | NOT NULL | |
| `slug` | varchar(255) | NOT NULL | |
| `body` | longtext | nullable | |
| `meta_title` | varchar(255) | nullable | |
| `meta_description` | varchar(500) | nullable | |
| `created_at` | timestamp | NOT NULL | |
| `updated_at` | timestamp | NOT NULL | |

> **Index:** `UNIQUE(locale, slug)`, `UNIQUE(locale, page_key)`

---

### Thay đổi table gốc
Thêm vào `products`, `categories`, `blog_posts`, `blog_categories`:
```sql
-- Xóa các column phụ thuộc locale khỏi table gốc
-- (name, slug, description đã chuyển vào _translations)
-- Giữ lại: price, stock, sku, is_active, sort_order, v.v.
```

> **Lưu ý:** Các column `name`, `slug`, `description` trong table gốc vẫn giữ lại như **fallback** (locale mặc định `vi`) cho backward compatibility trong sprint đầu, sau đó deprecate dần.

---

## 4. Locale Detection & Routing

### Middleware: `SetLocale`
```php
// app/Http/Middleware/SetLocale.php
class SetLocale {
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (!in_array($locale, config('app.supported_locales'))) {
            abort(404);
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
```

### config/app.php additions
```php
'supported_locales' => ['vi', 'en'],
'fallback_locale'   => 'vi',
```

### routes/web.php structure
```php
// Redirect root → detect locale
Route::get('/', function (Request $request) {
    $preferred = $request->getPreferredLanguage(['vi', 'en']) ?? 'vi';
    return redirect("/{$preferred}/");
});

// Tất cả route đều dùng /{locale}/ prefix
Route::prefix('{locale}')
    ->where(['locale' => 'vi|en'])
    ->middleware(['web', 'set.locale'])
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home');

        // Catalog
        Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('category.show');
        Route::get('products/{slug}', [ProductController::class, 'show'])->name('product.show');
        Route::get('search', [SearchController::class, 'index'])->name('search');

        // Blog
        Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
        Route::get('blog/categories/{slug}', [BlogController::class, 'category'])->name('blog.category');
        Route::get('blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

        // Trang tĩnh
        Route::get('{slug}', [PageController::class, 'show'])->name('page.show');
    });

// Fallback: không có locale prefix → redirect 301 về /vi/
Route::fallback(function (Request $request) {
    return redirect('/vi/' . $request->path(), 301);
});
```

### Helper: `route_locale()`
```php
// app/Helpers/LocaleHelper.php
function route_locale(string $name, string $locale, array $params = []): string
{
    return route($name, array_merge(['locale' => $locale], $params));
}

// Blade usage:
// {{ route_locale('product.show', 'en', ['slug' => $product->translation('en')->slug]) }}
```

---

## 5. SEO Layer — Multilingual

### `seo_meta` table — thêm column `locale`
```sql
ALTER TABLE seo_meta ADD COLUMN locale varchar(10) NOT NULL DEFAULT 'vi';
-- Index: UNIQUE(model_type, model_id, locale)
```

Mỗi model có **2 seo_meta rows** — 1 cho `vi`, 1 cho `en`.

### SeoMeta model — scope theo locale
```php
public function scopeForLocale(Builder $q, string $locale): Builder
{
    return $q->where('locale', $locale);
}
```

### HasSeoMeta trait — cập nhật
```php
public function seoMeta(string $locale = null): ?SeoMeta
{
    $locale ??= app()->getLocale();
    return $this->seoMetas()->forLocale($locale)->first();
}
```

### Blade SeoHead component — render đầy đủ
```php
// app/View/Components/SeoHead.php
// Nhận: $model, $locale, $alternateUrls (array locale→url)
// Render:
//   <title>
//   <meta name="description">
//   <link rel="canonical" href="{current url}">          ← tự trỏ về chính nó
//   <link rel="alternate" hreflang="vi" href="...">
//   <link rel="alternate" hreflang="en" href="...">
//   <link rel="alternate" hreflang="x-default" href="{vi url}">
```

### Hreflang generation — SeoService
```php
// Service: SeoService::hreflangTags(Model $model): array
// Return: ['vi' => 'https://...', 'en' => 'https://...']
// Controller truyền vào view: compact('alternateUrls')

// Blade:
@foreach($alternateUrls as $lang => $url)
    <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] }}" />
<link rel="canonical" href="{{ $alternateUrls[app()->getLocale()] }}" />
```

---

## 6. GEO Layer — Multilingual

### `geo_entity_profiles` — thêm `locale` column
```sql
ALTER TABLE geo_entity_profiles ADD COLUMN locale varchar(10) NOT NULL DEFAULT 'vi';
-- UNIQUE(model_type, model_id, locale)
```

GEO profile mô tả thực thể theo ngôn ngữ — tên địa phương, mô tả ngắn cho AI agents.
- `vi`: "Áo thun Uniqlo chất liệu cotton cao cấp"
- `en`: "Uniqlo premium cotton t-shirt"

---

## 7. JSON-LD — Multilingual

### Strategy
- Mỗi model × locale = 1 `jsonld_schemas` row
- `model_type` + `model_id` + `locale` → `UNIQUE`

### Schema thay đổi theo locale
```json
// /vi/products/ao-thun-uniqlo
{
  "@type": "Product",
  "name": "Áo thun Uniqlo cotton cao cấp",
  "url": "https://site.com/vi/products/ao-thun-uniqlo",
  "description": "...",
  "offers": { "priceCurrency": "VND", "price": "299000" }
}

// /en/products/uniqlo-t-shirt
{
  "@type": "Product",
  "name": "Uniqlo Premium Cotton T-Shirt",
  "url": "https://site.com/en/products/uniqlo-t-shirt",
  "description": "...",
  "offers": { "priceCurrency": "VND", "price": "299000" }
}
```

> **Tiền tệ (đã xác nhận):** Admin nhập giá trực tiếp — không auto-convert.
> `price` lấy từ DB (admin đã nhập đúng giá trị + đơn vị).
> `priceCurrency` lấy từ `CurrencyService::activeCurrency()->code` — tích hợp vào `JsonldService`.
> Cả `/vi/` lẫn `/en/` hiển thị cùng giá, cùng đơn vị tiền — vì đây là 1 shop, 1 giá.

### BreadcrumbList — multilingual
```json
// vi
[{"@type":"ListItem","position":1,"name":"Trang chủ","item":"https://site.com/vi/"},
 {"@type":"ListItem","position":2,"name":"Áo thun","item":"https://site.com/vi/categories/ao-thun"},
 {"@type":"ListItem","position":3,"name":"Uniqlo cotton"}]

// en
[{"@type":"ListItem","position":1,"name":"Home","item":"https://site.com/en/"},
 {"@type":"ListItem","position":2,"name":"T-Shirts","item":"https://site.com/en/categories/t-shirts"},
 {"@type":"ListItem","position":3,"name":"Uniqlo Cotton T-Shirt"}]
```

### Observer — sync cả hai locale
```php
class ProductObserver {
    public function saved(Product $product): void
    {
        foreach (config('app.supported_locales') as $locale) {
            dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
            dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
            dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
        }
    }
}
```

---

## 8. LLMs / AI Discoverability

### LLMs có cần phiên bản khác nhau theo ngôn ngữ không?
**Có — và đây KHÔNG phải duplicate content.** Lý do:

| Tiêu chí | LLMs per locale |
|---|---|
| Nội dung | Thực sự khác nhau — tiếng Việt vs tiếng Anh |
| Mục đích | AI agent đọc đúng ngôn ngữ của user query |
| Crawlers | GPT, Gemini, Perplexity crawl theo Accept-Language hoặc URL pattern |
| Google | Không index `llms.txt` (không phải HTML) → không có duplicate content risk |

### Cấu trúc file
```
/vi/llms.txt   ← AI agents query tiếng Việt
/en/llms.txt   ← AI agents query tiếng Anh
/llms.txt      ← (optional) redirect về /vi/llms.txt — entry point chung
```

```
# /vi/llms.txt
> Cửa hàng thời trang online — áo thun, quần jeans, phụ kiện
> Sản phẩm mới cập nhật hàng tuần

# Danh mục: Áo thun | Quần jeans | Phụ kiện
# URL: https://site.com/vi/

# /en/llms.txt
> Online fashion store — t-shirts, jeans, accessories
> New products updated weekly

# Categories: T-shirts | Jeans | Accessories
# URL: https://site.com/en/
```

### `llms_documents` — thêm `locale`
```sql
ALTER TABLE llms_documents ADD COLUMN locale varchar(10) NOT NULL DEFAULT 'vi';
-- UNIQUE(slug, locale)
```

### `llms_entries` — thêm `locale`
```sql
ALTER TABLE llms_entries ADD COLUMN locale varchar(10) NOT NULL DEFAULT 'vi';
-- Mỗi entity × locale = 1 entry riêng
```

### Route
```php
// Ngoài /{locale}/ group vì llms.txt không có locale trong path
Route::get('{locale}/llms.txt', [LlmsController::class, 'index'])
    ->where('locale', 'vi|en');

Route::get('llms.txt', fn() => redirect('/vi/llms.txt', 302));
```

---

## 9. Duplicate Content — Prevention Strategy

### Tại sao đa ngôn ngữ KHÔNG phải duplicate content (nếu làm đúng)
Google phân biệt duplicate content vs multilingual content qua 3 tín hiệu:

| Tín hiệu | Đúng | Sai → duplicate risk |
|---|---|---|
| `hreflang` | Có, đúng cú pháp, 2 chiều | Thiếu hoặc 1 chiều |
| `canonical` | Mỗi URL tự trỏ về chính nó | `/en/` canonical → `/vi/` |
| Nội dung | Thực sự khác ngôn ngữ | Cùng nội dung 2 URL |

### Nguồn gốc duplicate content thực sự — và cách xử lý

**Vấn đề 1 — Fallback hiển thị nội dung vi tại URL /en/**
```
SAI:  /en/products/ao-thun → render nội dung tiếng Việt → canonical: /en/... → DUPLICATE
ĐÚNG: /en/products/ao-thun (chưa dịch) → 302 redirect → /vi/products/ao-thun
```
> **Quyết định thay đổi:** Fallback KHÔNG hiển thị vi tại en URL.
> Thay vào đó: 302 redirect về /vi/ nếu translation en chưa tồn tại.
> Sau khi admin dịch xong → URL /en/ tự hoạt động, không cần làm gì thêm.

**Vấn đề 2 — URL không có locale prefix bị index**
```
SAI:  site.com/products/ao-thun (no locale) → bị crawl → duplicate với /vi/...
ĐÚNG: Robots.txt disallow / (no prefix) + 301 redirect tất cả về /vi/
```

**Vấn đề 3 — Pagination và filter params**
```
SAI:  /vi/products?page=2&sort=price → bị index → near-duplicate
ĐÚNG: <link rel="canonical" href="/vi/products"> trên tất cả paginated URLs
      Robots.txt: Disallow: *?sort=  (tùy chọn)
```

**Vấn đề 4 — Session/token URLs bị crawl**
```
ĐÚNG: Robots.txt disallow /api/, /admin/, /horizon/
```

### Fallback controller logic (thay đổi so với plan ban đầu)
```php
// ProductController::show()
public function show(string $locale, string $slug): Response
{
    $translation = ProductTranslation::where('locale', $locale)
                                     ->where('slug', $slug)
                                     ->first();

    if (!$translation) {
        // Không có translation cho locale này → tìm vi version
        $viTranslation = ProductTranslation::where('locale', 'vi')
                                           ->where('slug', $slug)
                                           ->first();

        if ($viTranslation) {
            // Redirect 302 (tạm thời — khi admin dịch xong sẽ resolve)
            return redirect("/{config('app.fallback_locale')}/products/{$viTranslation->slug}", 302);
        }

        abort(404);
    }

    // Có translation → render bình thường
    $product = $translation->product;
    $alternateUrls = $this->seoService->alternateUrls($product, 'product.show');

    return view('pages.product.show', compact('product', 'translation', 'alternateUrls'));
}
```

### Sitemap — chỉ include URL đã có translation
```php
// SyncSitemapEntry job — chỉ chạy khi translation tồn tại
dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
// Bên trong job: kiểm tra translation tồn tại mới upsert, ngược lại xóa entry
```

### Summary: 5 điều bắt buộc để không bị duplicate
```
1. hreflang đúng và 2 chiều trên MỌI trang
2. Canonical tự trỏ về chính mình (không trỏ sang locale khác)
3. Fallback = 302 redirect về /vi/ (không hiển thị vi content tại /en/ URL)
4. Sitemap chỉ chứa URL đã có translation thực
5. Robots.txt block /api/, /admin/, crawl param URLs
```

---

## 10. Sitemap — Multilingual

### Cấu trúc sitemap
Hiện tại có **4 child sitemaps** → multilingual thành **8 child sitemaps** (4 × 2 locale):

```xml
<!-- /sitemap.xml (sitemap index) -->
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <!-- Tiếng Việt -->
  <sitemap><loc>https://site.com/sitemap-vi-products.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-vi-product-categories.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-vi-blog.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-vi-blog-categories.xml</loc></sitemap>

  <!-- English -->
  <sitemap><loc>https://site.com/sitemap-en-products.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-en-product-categories.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-en-blog.xml</loc></sitemap>
  <sitemap><loc>https://site.com/sitemap-en-blog-categories.xml</loc></sitemap>

</sitemapindex>
```

```xml
<!-- sitemap-vi-products.xml — mỗi URL có hreflang xlinks -->
<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://site.com/vi/products/ao-thun-uniqlo</loc>
    <xhtml:link rel="alternate" hreflang="vi" href="https://site.com/vi/products/ao-thun-uniqlo"/>
    <xhtml:link rel="alternate" hreflang="en" href="https://site.com/en/products/uniqlo-t-shirt"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="https://site.com/vi/products/ao-thun-uniqlo"/>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
</urlset>
```

> **Naming convention child sitemaps:** `sitemap-{locale}-{type}.xml`
> Types: `products`, `product-categories`, `blog`, `blog-categories`

### `sitemap_indexes` seeder — 8 rows (2 locale × 4 type)
```php
// Mỗi locale × type = 1 row trong sitemap_indexes
['slug' => 'vi-products',           'path' => '/sitemap-vi-products.xml']
['slug' => 'vi-product-categories', 'path' => '/sitemap-vi-product-categories.xml']
['slug' => 'vi-blog',               'path' => '/sitemap-vi-blog.xml']
['slug' => 'vi-blog-categories',    'path' => '/sitemap-vi-blog-categories.xml']
// ... tương tự cho en
```

### `sitemap_entries` — thêm `locale`
```sql
ALTER TABLE sitemap_entries ADD COLUMN locale varchar(10) NOT NULL DEFAULT 'vi';
-- Mỗi URL = 1 row: UNIQUE(url) đã cover vì URL chứa locale prefix
-- Index: (sitemap_index_id, locale)
```

### Robots.txt — chuẩn cho path prefix multilingual
```
User-agent: *

# Cho phép crawl public locale paths
Allow: /vi/
Allow: /en/

# Block admin + internal
Disallow: /admin/
Disallow: /horizon/
Disallow: /api/
Disallow: /telescope/

# Block URLs không có locale prefix (đều redirect về /vi/ dù sao)
Disallow: /products/
Disallow: /categories/
Disallow: /blog/

# Block filter/sort params (near-duplicate risk)
Disallow: /*?sort=
Disallow: /*?filter=

# Sitemap
Sitemap: https://site.com/sitemap.xml
```

> **Lưu ý:** Không cần `Allow: /` vì Googlebot mặc định được phép. Chỉ cần block những gì không muốn index.
> `Disallow: /products/` block `/products/abc` (no locale) — những URL này đã có 301 redirect về `/vi/` rồi, nhưng block thêm để Googlebot không tốn crawl budget.

### Google Search Console — submit
- Submit `/sitemap.xml` (index) — GSC tự discover 8 child sitemaps
- Verify cả property `vi` lẫn `en` nếu dùng Search Console per locale (optional)
- Không cần submit từng child sitemap riêng

---

## 10. Redirects — Multilingual

### Khi slug thay đổi → tạo redirect per locale
```php
// ProductObserver::updating()
foreach (config('app.supported_locales') as $locale) {
    $oldTranslation = $product->translations()->where('locale', $locale)->getOriginal();
    $newTranslation = $product->translations()->where('locale', $locale)->first();

    if ($oldTranslation?->slug !== $newTranslation?->slug) {
        Redirect::create([
            'from_path' => "/{$locale}/products/{$oldTranslation->slug}",
            'to_path'   => "/{$locale}/products/{$newTranslation->slug}",
            'status_code' => 301,
            'locale'    => $locale,
        ]);
    }
}
```

### `redirects` table — thêm `locale`
```sql
ALTER TABLE redirects ADD COLUMN locale varchar(10) nullable;
-- null = áp dụng cho tất cả locale
-- 'vi' / 'en' = chỉ áp dụng locale đó
```

---

## 11. Admin — Filament Multilingual Input

### Pattern: Tab per locale trong Filament
```php
// ProductResource::form()
Forms\Components\Tabs::make('Translations')
    ->tabs([
        Forms\Components\Tabs\Tab::make('Tiếng Việt (vi)')
            ->schema([
                TextInput::make('translations.vi.name')->required(),
                TextInput::make('translations.vi.slug')->unique(),
                RichEditor::make('translations.vi.description'),
                TextInput::make('translations.vi.meta_title'),
                Textarea::make('translations.vi.meta_description'),
            ]),
        Forms\Components\Tabs\Tab::make('English (en)')
            ->schema([
                TextInput::make('translations.en.name')->required(),
                TextInput::make('translations.en.slug')->unique(),
                RichEditor::make('translations.en.description'),
                TextInput::make('translations.en.meta_title'),
                Textarea::make('translations.en.meta_description'),
            ]),
    ]),
```

### Auto-slug generation
- Admin nhập `name` → JS auto-generate slug (slug-vi hoặc slug-en)
- Filament hook: `afterStateUpdated` trên `name` → fill `slug` field
- Nếu slug đã tồn tại → append `-2`, `-3`, v.v.

### Thêm locale sau — chỉ cần:
1. Thêm `ja` vào `config('app.supported_locales')`
2. Thêm Tab mới trong Filament form
3. Chạy seeder để tạo default translations

---

## 12. Blade Views Strategy

### Layout structure
```
resources/views/
├── layouts/
│   ├── app.blade.php          ← Master layout (locale-aware head)
│   └── partials/
│       ├── head.blade.php     ← SEO meta, hreflang, canonical
│       ├── header.blade.php   ← Nav với locale switcher
│       └── footer.blade.php
├── pages/
│   ├── home.blade.php
│   ├── product/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── category/
│   │   └── show.blade.php
│   ├── blog/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── static/
│       └── show.blade.php
└── components/
    ├── seo/
    │   ├── head.blade.php        ← <title>, <meta>, canonical
    │   ├── hreflang.blade.php    ← hreflang tags
    │   └── jsonld.blade.php      ← <script type="application/ld+json">
    ├── product/
    │   ├── card.blade.php
    │   └── grid.blade.php
    └── ui/
        ├── locale-switcher.blade.php  ← Dropdown vi/en
        └── pagination.blade.php
```

### Locale switcher component
```blade
{{-- Hiển thị link sang locale khác với đúng slug --}}
@foreach(config('app.supported_locales') as $locale)
    @if($locale !== app()->getLocale())
        <a href="{{ $alternateUrls[$locale] ?? route('home', ['locale' => $locale]) }}">
            {{ strtoupper($locale) }}
        </a>
    @endif
@endforeach
```

### Strings tĩnh — lang files
```
lang/
├── vi/
│   ├── common.php    ← "Thêm vào giỏ", "Tìm kiếm", "Trang chủ"
│   ├── product.php
│   └── blog.php
└── en/
    ├── common.php    ← "Add to cart", "Search", "Home"
    ├── product.php
    └── blog.php
```

---

## 13. Sprint Build Order

> Build từng sprint, test xong mới chuyển.

| Sprint | Task | Output |
|---|---|---|
| **ML-01** | Thêm `supported_locales` config + `locale` column vào DB tables | Config + migration |
| **ML-02** | Tạo `_translations` tables cho product, category, blog_post, blog_category, page | Migrations |
| **ML-03** | Models + relationships (`hasMany translations`, `translation($locale)` helper) | Models |
| **ML-04** | `SetLocale` middleware + route structure `/{locale}/` | Middleware + routes |
| **ML-05** | Cập nhật `seo_meta` + `HasSeoMeta` trait để support locale | Trait update |
| **ML-06** | `SeoHead` Blade component (title, meta, canonical, hreflang) | Component |
| **ML-07** | `JsonldRenderer` Blade component — multilingual | Component |
| **ML-08** | Cập nhật Observers → dispatch jobs per locale | Observer update |
| **ML-09** | Cập nhật `SyncJsonldSchema`, `SyncSitemapEntry`, `SyncLlmsEntry` jobs | Jobs update |
| **ML-10** | Sitemap — multilingual child sitemaps + hreflang xlinks | Sitemap |
| **ML-11** | LLMs — `/{locale}/llms.txt` routes | Route + controller |
| **ML-12** | Redirects — per locale khi slug thay đổi | Observer + service |
| **ML-13** | Filament — tabs per locale trong Product + Category resource | Filament |
| **ML-14** | Filament — tabs per locale trong BlogPost + BlogCategory | Filament |
| **ML-15** | Blade layouts + locale switcher component | Views |
| **ML-16** | Controllers — resolve slug từ `_translations` table | Controllers |
| **ML-17** | Lang files vi + en cho strings tĩnh | Lang files |
| **ML-18** | Feature tests — route resolution, SEO tags, hreflang | Tests |

---

## 14. Packages

### Thêm mới (vào Backbone hiện có)
```bash
# Không cần package i18n nào — Laravel built-in đủ dùng
# Chỉ cần config + middleware + translation tables

# Optional: nếu muốn auto-translate slug
composer require cocur/slugify    # Slug từ tiếng Việt có dấu → không dấu
```

### Không cần
- ❌ `spatie/laravel-translatable` — ta dùng custom `_translations` tables (flexible hơn, dễ query, dễ index)
- ❌ `mcamara/laravel-localization` — overkill, ta control routing thủ công
- ❌ `astrotomic/laravel-translatable` — tương tự, không cần

---

## 16. Checklist trước khi build

```
✅ Locale default: vi (có prefix /vi/ trong URL)
✅ Domain: path prefix /{locale}/ — không dùng subdomain
✅ Image path: dùng chung, alt_text derive từ translation trong Blade
✅ Sitemap: 8 child sitemaps (4 type × 2 locale) — chỉ URL đã có translation
✅ Tiền tệ: admin nhập giá, CurrencyService::activeCurrency() → priceCurrency
✅ Fallback translation: 302 redirect về /vi/ nếu en chưa dịch (không hiển thị vi tại en URL)
✅ Slug: không dấu — /vi/ao-thun, /en/t-shirt
✅ Canonical: mỗi trang tự trỏ canonical về chính nó (self-referencing)
✅ x-default hreflang → /vi/ (Google fallback, không phải "vi là canonical của toàn site")
✅ LLMs: /vi/llms.txt + /en/llms.txt riêng biệt — không phải duplicate content
✅ Robots.txt: Allow /vi/ + /en/, Disallow /admin/ /api/ filter params
✅ Duplicate content: 5 biện pháp phòng (hreflang 2 chiều, canonical, 302 fallback, sitemap sạch, robots.txt)
✅ Google Search Console: submit /sitemap.xml (discover 8 child tự động)
   → Horizon phải đang chạy — SyncSitemapEntry/SyncJsonldSchema/SyncLlmsEntry đều là queued jobs trên queue `seo`
   → Nếu Horizon không chạy, jobs tồn đọng, sitemap/jsonld/llms không cập nhật
✅ Giá sản phẩm: admin tự nhập giá cho cả vi lẫn en — KHÔNG auto-convert
   → Thêm `price` + `currency` vào `product_translations` để admin nhập riêng per locale
   → JSON-LD lấy price + currency từ translation của locale hiện tại, không qua CurrencyService
✅ Test hreflang với Google Search Console → URL Inspection sau khi deploy
```

---

## Quyết định đã xác nhận (rev 3)

| # | Vấn đề | Quyết định |
|---|---|---|
| 1 | **Image path** | Dùng chung — cùng file cho vi + en. `alt_text` derive từ `product_translations.name` trong Blade |
| 2 | **Sitemap** | 8 child sitemaps: 4 type × 2 locale. Chỉ include URL đã có translation thực |
| 3 | **Tiền tệ** | Admin nhập `price` + `currency` riêng per locale trong `product_translations`. Không auto-convert. Fallback về `products.price` nếu chưa nhập |
| 4 | **Fallback translation** | **302 redirect về /vi/** nếu en chưa dịch — KHÔNG hiển thị vi tại en URL (duplicate content) |
| 5 | **Slug** | Không dấu — `/vi/ao-thun`, `/en/t-shirt` |
| 6 | **Canonical** | Mỗi trang tự trỏ canonical về chính nó (self-referencing). `vi` là `x-default` trong hreflang |
| 7 | **Domain** | Path prefix `/{locale}/` — không dùng subdomain |
| 8 | **LLMs** | 2 file riêng: `/vi/llms.txt` + `/en/llms.txt`. Không phải duplicate (nội dung khác ngôn ngữ) |
| 9 | **Robots.txt** | Allow `/vi/` + `/en/`. Disallow `/admin/`, `/api/`, filter params |

### Giải thích canonical — chỉ 1 canonical per page
```
Sai: "vi là canonical của toàn site, en không có canonical"
Đúng:
  /vi/products/ao-thun  → <link rel="canonical" href="/vi/products/ao-thun">  (tự trỏ)
  /en/products/t-shirt  → <link rel="canonical" href="/en/products/t-shirt">  (tự trỏ)
  Cả 2 trang đều có hreflang trỏ qua lại nhau
  hreflang x-default → /vi/... (Google fallback khi không match locale user)
```

### Giải thích fallback — tại sao phải redirect thay vì hiển thị
```
Sai (duplicate content):
  /en/products/ao-thun → render nội dung TIẾNG VIỆT → canonical: /en/... → Google thấy 2 URL cùng content

Đúng (không duplicate):
  /en/products/ao-thun (chưa có translation) → 302 redirect → /vi/products/ao-thun
  → Google biết /en/ chưa sẵn sàng, theo dõi /vi/ version
  → Khi admin dịch xong → /en/ URL hoạt động bình thường, hết redirect
```

---

*Tất cả quyết định đã xác nhận — sẵn sàng bắt đầu sprint ML-01.*
