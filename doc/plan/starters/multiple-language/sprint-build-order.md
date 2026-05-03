# Multilingual Sprint Build Order
> **For:** Claude Code CLI (AI-assisted development)
> **Project:** Backbone — Laravel 13 + Blade, Multilingual vi + en
> **Rule:** Một sprint = một task. Test xong mới chuyển. Không skip bước.
> **Prerequisite:** Backbone base đã build xong (S00–S60 trong Backend Build Plan.md)
> **Reference:** `doc/plan/starters/multiple-language/multilingual-architecture.md`
> **Last Updated:** April 2026

---

## Quyết định đã chốt — đọc trước khi build

| Điểm | Quyết định |
|---|---|
| URL | `/{locale}/{segment}` cho mọi locale kể cả vi |
| Fallback | 302 redirect về `/vi/` nếu en chưa có translation — KHÔNG hiển thị vi tại en URL |
| Canonical | Mỗi trang self-referencing canonical. `x-default` hreflang → `/vi/` |
| Hreflang | 2 chiều, xuất hiện trên CẢ HAI phiên bản ngôn ngữ |
| Slug | Không dấu — `ao-thun` không phải `áo-thun` |
| Giá | Admin nhập `price` + `currency` riêng per locale trong `product_translations`. Fallback về `products.price` |
| Image | Dùng chung. `alt` derive từ `translation->name` trong Blade |
| Sitemap | 8 child sitemaps (4 type × 2 locale). Chỉ include URL đã có translation thực |
| LLMs | `/vi/llms.txt` + `/en/llms.txt` riêng. `/llms.txt` redirect về `/vi/llms.txt` |
| Horizon | BẮT BUỘC chạy — SEO jobs (`SyncSitemapEntry`, `SyncJsonldSchema`, `SyncLlmsEntry`) chạy trên queue `seo` |
| Sitemap index | 8 rows trong `sitemap_indexes`: `vi-products`, `vi-product-categories`, `vi-blog`, `vi-blog-categories`, `en-*` tương tự |

---

## Sprint Index

| Sprint | Task | Phụ thuộc |
|---|---|---|
| ML-01 | Config + Migration: thêm `locale` vào existing tables | Base S00–S08 done |
| ML-02 | Migrations: `_translations` tables | ML-01 |
| ML-03 | Models + Translation relationships | ML-02 |
| ML-04 | `SetLocale` middleware + Web routes | ML-03 |
| ML-05 | `HasSeoMeta` trait — multilingual | ML-04 |
| ML-06 | `SeoHead` Blade component (canonical + hreflang) | ML-05 |
| ML-07 | `JsonldRenderer` Blade component — multilingual | ML-06 |
| ML-08 | Observers — dispatch jobs per locale | ML-07 |
| ML-09 | Jobs: `SyncJsonldSchema`, `SyncSitemapEntry`, `SyncLlmsEntry` — multilingual | ML-08 |
| ML-10 | Sitemap — 8 child sitemaps + hreflang xlinks | ML-09 |
| ML-11 | LLMs — `/vi/llms.txt` + `/en/llms.txt` | ML-10 |
| ML-12 | Redirects — per locale khi slug thay đổi | ML-11 |
| ML-13 | Filament — Product + Category multilingual form | ML-12 |
| ML-14 | Filament — BlogPost + BlogCategory multilingual form | ML-13 |
| ML-15 | Blade layouts + locale switcher component | ML-14 |
| ML-16 | Controllers — resolve slug từ `_translations` + 302 fallback | ML-15 |
| ML-17 | Lang files vi + en cho strings tĩnh | ML-16 |
| ML-18 | Robots.txt | ML-17 |
| ML-19 | Feature tests — route, SEO tags, hreflang, fallback | ML-18 |

---

## ML-01 — Config + Migration: thêm `locale` vào existing tables

```
Mục tiêu: Thêm cột locale vào các bảng SEO/GEO hiện có + config app.

Bước 1 — config/app.php
Thêm vào array:
  'supported_locales' => ['vi', 'en'],
  'fallback_locale'   => 'vi',
  'default_currency'  => 'VND',

Bước 2 — Migration: xxxx_add_locale_to_seo_tables
Tạo 1 migration duy nhất chứa tất cả các alter:

  // seo_meta — thêm locale, đổi unique index
  $table->string('locale', 10)->default('vi')->after('model_id');
  // Xóa unique cũ (model_type, model_id) nếu có
  // Thêm unique mới: (model_type, model_id, locale)
  $table->unique(['model_type', 'model_id', 'locale']);

  // geo_entity_profiles — thêm locale
  $table->string('locale', 10)->default('vi')->after('model_id');
  $table->unique(['model_type', 'model_id', 'locale']);

  // jsonld_schemas — thêm locale
  $table->string('locale', 10)->default('vi')->after('model_id');
  $table->unique(['model_type', 'model_id', 'locale']);

  // sitemap_entries — thêm locale
  $table->string('locale', 10)->default('vi')->after('sitemap_index_id');
  $table->index(['sitemap_index_id', 'locale']);

  // llms_documents — thêm locale
  $table->string('locale', 10)->default('vi')->after('id');
  $table->unique(['slug', 'locale']);

  // llms_entries — thêm locale
  $table->string('locale', 10)->default('vi')->after('model_id');
  $table->unique(['model_type', 'model_id', 'locale']);

  // redirects — thêm locale (nullable = áp dụng mọi locale)
  $table->string('locale', 10)->nullable()->after('status_code');
  $table->index('locale');

Bước 3 — Seeder: sitemap_indexes
Cập nhật SitemapIndexSeeder để có 8 rows thay vì 4:
  ['slug' => 'vi-products',            'path' => '/sitemap-vi-products.xml',           'model_type' => 'product',       'locale' => 'vi']
  ['slug' => 'vi-product-categories',  'path' => '/sitemap-vi-product-categories.xml', 'model_type' => 'category',      'locale' => 'vi']
  ['slug' => 'vi-blog',                'path' => '/sitemap-vi-blog.xml',               'model_type' => 'blog_post',     'locale' => 'vi']
  ['slug' => 'vi-blog-categories',     'path' => '/sitemap-vi-blog-categories.xml',    'model_type' => 'blog_category', 'locale' => 'vi']
  ['slug' => 'en-products',            'path' => '/sitemap-en-products.xml',           'model_type' => 'product',       'locale' => 'en']
  ['slug' => 'en-product-categories',  'path' => '/sitemap-en-product-categories.xml', 'model_type' => 'category',      'locale' => 'en']
  ['slug' => 'en-blog',                'path' => '/sitemap-en-blog.xml',               'model_type' => 'blog_post',     'locale' => 'en']
  ['slug' => 'en-blog-categories',     'path' => '/sitemap-en-blog-categories.xml',    'model_type' => 'blog_category', 'locale' => 'en']
  → Tambah column `locale` vào sitemap_indexes table juga.

Bước 4 — Run
  php artisan migrate
  php artisan db:seed --class=SitemapIndexSeeder

Verify:
  php artisan db:table seo_meta         → có column locale
  php artisan db:table sitemap_indexes  → có 8 rows
```

---

## ML-02 — Migrations: `_translations` tables

```
Mục tiêu: Tạo 5 translation tables. Đây là core của multilingual.

Chạy theo thứ tự sau — FK phụ thuộc table gốc phải tồn tại trước.

1. xxxx_create_product_translations_table
   - id: bigIncrements PK
   - product_id: uuid FK → products.id cascadeOnDelete
   - locale: string(10) NOT NULL
   - name: string(500) NOT NULL
   - slug: string(600) NOT NULL
   - short_description: text nullable
   - description: longText nullable        ← TinyMCE content
   - price: decimal(12,2) nullable         ← admin nhập per locale, null = dùng products.price
   - currency: string(10) nullable         ← 'VND', 'USD'. null = dùng config('app.default_currency')
   - meta_title: string(255) nullable
   - meta_description: string(500) nullable
   - timestamps
   - UNIQUE(locale, slug)
   - INDEX(product_id, locale)

2. xxxx_create_category_translations_table
   - id: bigIncrements PK
   - category_id: unsignedBigInteger FK → categories.id cascadeOnDelete
   - locale: string(10) NOT NULL
   - name: string(255) NOT NULL
   - slug: string(300) NOT NULL
   - description: text nullable
   - meta_title: string(255) nullable
   - meta_description: string(500) nullable
   - timestamps
   - UNIQUE(locale, slug)
   - INDEX(category_id, locale)

3. xxxx_create_blog_post_translations_table
   - id: bigIncrements PK
   - blog_post_id: uuid FK → blog_posts.id cascadeOnDelete
   - locale: string(10) NOT NULL
   - title: string(500) NOT NULL
   - slug: string(600) NOT NULL
   - excerpt: text nullable
   - body: longText nullable
   - meta_title: string(255) nullable
   - meta_description: string(500) nullable
   - timestamps
   - UNIQUE(locale, slug)
   - INDEX(blog_post_id, locale)

4. xxxx_create_blog_category_translations_table
   - id: bigIncrements PK
   - blog_category_id: unsignedBigInteger FK → blog_categories.id cascadeOnDelete
   - locale: string(10) NOT NULL
   - name: string(255) NOT NULL
   - slug: string(300) NOT NULL
   - description: text nullable
   - meta_title: string(255) nullable
   - meta_description: string(500) nullable
   - timestamps
   - UNIQUE(locale, slug)
   - INDEX(blog_category_id, locale)

5. xxxx_create_page_translations_table
   - id: bigIncrements PK
   - page_key: string(100) NOT NULL    ← 'about', 'contact', 'faq'
   - locale: string(10) NOT NULL
   - title: string(255) NOT NULL
   - slug: string(255) NOT NULL
   - body: longText nullable
   - meta_title: string(255) nullable
   - meta_description: string(500) nullable
   - is_active: boolean default true
   - timestamps
   - UNIQUE(locale, slug)
   - UNIQUE(locale, page_key)

Run: php artisan migrate
Verify: php artisan db:table product_translations
```

---

## ML-03 — Models + Translation Relationships

```
Mục tiêu: Tạo Translation models + thêm relationships vào models gốc.
Reference: doc/folderstruct.md → app/Models/

File tạo mới:
  app/Models/ProductTranslation.php
  app/Models/CategoryTranslation.php
  app/Models/BlogPostTranslation.php
  app/Models/BlogCategoryTranslation.php
  app/Models/PageTranslation.php

Mỗi Translation model:
  - extends Model
  - protected $fillable = [tất cả columns trừ id, timestamps]
  - KHÔNG dùng SoftDeletes (translation xóa theo parent qua CASCADE)
  - Không có $casts đặc biệt ngoài timestamps

Thêm vào Product model:
  // Relationships
  public function translations(): HasMany
  {
      return $this->hasMany(ProductTranslation::class);
  }

  // Helper: lấy translation theo locale, fallback về vi nếu không có
  public function translation(string $locale = null): ?ProductTranslation
  {
      $locale ??= app()->getLocale();
      return $this->translations->firstWhere('locale', $locale)
          ?? $this->translations->firstWhere('locale', 'vi');
  }

  // Helper: price theo locale (fallback về products.price)
  public function localizedPrice(string $locale = null): string
  {
      $t = $this->translation($locale);
      return $t?->price ?? $this->price;
  }

  // Helper: currency theo locale
  public function localizedCurrency(string $locale = null): string
  {
      $t = $this->translation($locale);
      return $t?->currency ?? config('app.default_currency');
  }

Làm tương tự cho: Category, BlogPost, BlogCategory

PageTranslation model:
  - Không có parent model — standalone
  - Thêm scopeForLocale($locale) + scopeByKey($key)

Thêm vào AppServiceProvider morphMap (nếu PageTranslation cần morph — thường không cần):
  Không cần thêm — PageTranslation không dùng polymorphic.

Verify:
  php artisan tinker
  → Product::first()->translations    (phải trả về collection)
  → Product::first()->translation('vi')  (phải trả về object hoặc null)
```

---

## ML-04 — `SetLocale` Middleware + Web Routes

```
Mục tiêu: Mọi request có /{locale}/ prefix đều set locale đúng.

File tạo mới: app/Http/Middleware/SetLocale.php
  public function handle(Request $request, Closure $next): Response
  {
      $locale = $request->route('locale');
      if (!in_array($locale, config('app.supported_locales'), true)) {
          abort(404);
      }
      app()->setLocale($locale);
      \Carbon\Carbon::setLocale($locale);
      return $next($request);
  }

Đăng ký middleware trong bootstrap/app.php (Laravel 13 style):
  ->withMiddleware(function (Middleware $middleware) {
      $middleware->alias(['set.locale' => \App\Http\Middleware\SetLocale::class]);
  })

File sửa: routes/web.php
  Cấu trúc hoàn chỉnh:

  // Root redirect → detect Accept-Language
  Route::get('/', function (Request $request) {
      $preferred = $request->getPreferredLanguage(config('app.supported_locales')) ?? 'vi';
      return redirect("/{$preferred}/", 302);
  });

  // LLMs (ngoài locale group — path đặc biệt)
  Route::get('{locale}/llms.txt', [\App\Http\Controllers\Web\LlmsController::class, 'index'])
      ->where('locale', 'vi|en');
  Route::get('llms.txt', fn() => redirect('/vi/llms.txt', 302));

  // Locale group
  Route::prefix('{locale}')
      ->where(['locale' => implode('|', config('app.supported_locales'))])
      ->middleware(['web', 'set.locale'])
      ->group(function () {

          Route::get('/', [\App\Http\Controllers\Web\HomeController::class, 'index'])
              ->name('home');

          // Catalog
          Route::get('categories/{slug}', [\App\Http\Controllers\Web\CategoryController::class, 'show'])
              ->name('category.show');
          Route::get('products/{slug}', [\App\Http\Controllers\Web\ProductController::class, 'show'])
              ->name('product.show');
          Route::get('search', [\App\Http\Controllers\Web\SearchController::class, 'index'])
              ->name('search');

          // Blog
          Route::get('blog', [\App\Http\Controllers\Web\BlogController::class, 'index'])
              ->name('blog.index');
          Route::get('blog/categories/{slug}', [\App\Http\Controllers\Web\BlogController::class, 'category'])
              ->name('blog.category');
          Route::get('blog/{slug}', [\App\Http\Controllers\Web\BlogController::class, 'show'])
              ->name('blog.show');

          // Static pages — PHẢI đặt CUỐI CÙNG trong group
          Route::get('{slug}', [\App\Http\Controllers\Web\PageController::class, 'show'])
              ->name('page.show');
      });

  // Fallback: URL không có locale → 301 về /vi/
  Route::fallback(function (Request $request) {
      return redirect('/vi/' . ltrim($request->path(), '/'), 301);
  });

File tạo helper: app/Helpers/LocaleHelper.php
  if (!function_exists('route_locale')) {
      function route_locale(string $name, string $locale, array $params = []): string
      {
          return route($name, array_merge(['locale' => $locale], $params));
      }
  }

Đăng ký helper trong composer.json → autoload.files:
  "autoload": {
      "files": ["app/Helpers/LocaleHelper.php"]
  }
  → composer dump-autoload

Verify:
  php artisan route:list | grep '{locale}'
  → Thấy tất cả routes với prefix {locale}
  curl -I http://localhost/                    → 302 về /vi/ hoặc /en/
  curl -I http://localhost/vi/                 → 200
  curl -I http://localhost/en/                 → 200
  curl -I http://localhost/products/test       → 301 về /vi/products/test
```

---

## ML-05 — `HasSeoMeta` Trait — Multilingual

```
Mục tiêu: Trait hiện có support locale — mỗi model có 2 seo_meta rows.

File sửa: app/Traits/HasSeoMeta.php

Thay đổi:
  // Cũ: return $this->morphOne(SeoMeta::class, 'model')
  // Mới: hasMany + scope theo locale

  public function seoMetas(): MorphMany
  {
      return $this->morphMany(SeoMeta::class, 'model');
  }

  public function seoMeta(string $locale = null): ?SeoMeta
  {
      $locale ??= app()->getLocale();
      return $this->seoMetas->firstWhere('locale', $locale)
          ?? $this->seoMetas->firstWhere('locale', 'vi');
  }

File sửa: app/Models/Seo/SeoMeta.php
  Thêm:
  protected $fillable = [...fields hiện có..., 'locale'];

  // Scope
  public function scopeForLocale(Builder $q, string $locale): Builder
  {
      return $q->where('locale', $locale);
  }

File sửa: app/Traits/HasGeoProfile.php (tương tự)
  - geoProfile(string $locale = null)
  - Thêm locale vào fillable của GeoEntityProfile model

File sửa: app/Traits/HasJsonldSchemas.php (tương tự)
  - jsonldSchema(string $locale = null)

File sửa: app/Traits/HasSitemapEntry.php (tương tự)
  - sitemapEntry(string $locale = null)

File sửa: app/Traits/HasLlmsEntry.php (tương tự)
  - llmsEntry(string $locale = null)

Verify:
  php artisan tinker
  → $p = Product::first()
  → $p->seoMeta('vi')   // null nếu chưa có data, không lỗi
  → $p->seoMeta('en')   // null, không lỗi
```

---

## ML-06 — `SeoHead` Blade Component (Canonical + Hreflang)

```
Mục tiêu: Component render đầy đủ head SEO cho mọi locale.
KHÔNG được canonical trỏ sang locale khác — chỉ self-referencing.
Hreflang PHẢI xuất hiện trên cả hai phiên bản ngôn ngữ.

File tạo: app/View/Components/SeoHead.php
  Constructor nhận:
    - ?SeoMeta $seoMeta
    - string $currentUrl          ← URL hiện tại (canonical)
    - array $alternateUrls        ← ['vi' => 'https://...', 'en' => 'https://...']
    - string $fallbackTitle = ''
    - string $fallbackDescription = ''

  public function render(): View
  {
      return view('components.seo.head');
  }

File tạo: resources/views/components/seo/head.blade.php
  <title>{{ $seoMeta?->title ?? $fallbackTitle }} — {{ config('app.name') }}</title>
  <meta name="description" content="{{ $seoMeta?->description ?? $fallbackDescription }}">

  {{-- Canonical — LUÔN tự trỏ về chính mình --}}
  <link rel="canonical" href="{{ $currentUrl }}" />

  {{-- Hreflang — xuất hiện trên CẢ HAI phiên bản --}}
  @foreach($alternateUrls as $lang => $url)
      <link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
  @endforeach
  {{-- x-default → vi --}}
  <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['vi'] ?? $currentUrl }}" />

  @if($seoMeta?->og_title)
      <meta property="og:title" content="{{ $seoMeta->og_title }}" />
      <meta property="og:description" content="{{ $seoMeta->og_description }}" />
      <meta property="og:url" content="{{ $currentUrl }}" />
  @endif

File tạo: app/Services/SeoService.php
  Thêm method:
  public function alternateUrls(Model $model, string $routeName): array
  {
      $urls = [];
      foreach (config('app.supported_locales') as $locale) {
          $translation = $model->translation($locale);
          if ($translation) {
              $urls[$locale] = route($routeName, [
                  'locale' => $locale,
                  'slug'   => $translation->slug,
              ]);
          }
      }
      return $urls;
  }

Verify:
  Tạo 1 Blade test view, render <x-seo-head> với mock data
  → Kiểm tra output HTML có đủ: title, canonical, hreflang vi, hreflang en, x-default
  → canonical phải KHÁC hreflang (canonical = current URL, hreflang có cả 2)
```

---

## ML-07 — `JsonldRenderer` Blade Component — Multilingual

```
Mục tiêu: Component render JSON-LD đúng locale — name, url, price, currency theo locale.

File sửa: app/Services/JsonldService.php
  Thêm method buildProductSchema(Product $product, string $locale): array
  {
      $t = $product->translation($locale);
      return [
          '@context' => 'https://schema.org',
          '@type'    => 'Product',
          'name'     => $t?->name ?? $product->name,
          'url'      => route('product.show', ['locale' => $locale, 'slug' => $t?->slug]),
          'description' => strip_tags($t?->short_description ?? ''),
          'sku'      => $product->sku,
          'offers'   => [
              '@type'         => 'Offer',
              'priceCurrency' => $t?->currency ?? config('app.default_currency'),
              'price'         => $t?->price ?? $product->price,
              'availability'  => $product->stock_quantity > 0
                                 ? 'https://schema.org/InStock'
                                 : 'https://schema.org/OutOfStock',
          ],
      ];
  }

  Thêm method buildBreadcrumb(array $items): array
  // items = [['name' => '...', 'url' => '...'], ...]
  {
      return [
          '@context' => 'https://schema.org',
          '@type'    => 'BreadcrumbList',
          'itemListElement' => collect($items)->map(fn($item, $i) => [
              '@type'    => 'ListItem',
              'position' => $i + 1,
              'name'     => $item['name'],
              'item'     => $item['url'],
          ])->values()->all(),
      ];
  }

File sửa: resources/views/components/seo/jsonld.blade.php
  @if(!empty($schemas))
      @foreach($schemas as $schema)
          <script type="application/ld+json">
              {!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
          </script>
      @endforeach
  @endif

Verify:
  php artisan tinker
  → app(JsonldService::class)->buildProductSchema(Product::first(), 'vi')
  → Kiểm tra 'url' có chứa '/vi/', 'name' là tiếng Việt
  → app(JsonldService::class)->buildProductSchema(Product::first(), 'en')
  → Kiểm tra 'url' có chứa '/en/'
```

---

## ML-08 — Observers — Dispatch Jobs Per Locale

```
Mục tiêu: Mỗi lần save model → dispatch job cho TẤT CẢ supported locales.

File sửa: app/Observers/ProductObserver.php
  public function saved(Product $product): void
  {
      foreach (config('app.supported_locales') as $locale) {
          // Chỉ dispatch nếu translation locale này tồn tại
          if ($product->translations()->where('locale', $locale)->exists()) {
              dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
              dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
              dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
          }
      }
  }

  public function updating(Product $product): void
  {
      // Redirect handling — xem ML-12
  }

  public function deleted(Product $product): void
  {
      foreach (config('app.supported_locales') as $locale) {
          dispatch(new RemoveSitemapEntry($product, $locale))->onQueue('seo');
      }
  }

Làm tương tự cho: CategoryObserver, BlogPostObserver, BlogCategoryObserver

ProductTranslation Observer — QUAN TRỌNG:
  File tạo: app/Observers/ProductTranslationObserver.php
  // Khi translation được save → trigger product observer
  public function saved(ProductTranslation $translation): void
  {
      $product = $translation->product;
      $locale  = $translation->locale;
      dispatch(new SyncJsonldSchema($product, $locale))->onQueue('seo');
      dispatch(new SyncSitemapEntry($product, $locale))->onQueue('seo');
      dispatch(new SyncLlmsEntry($product, $locale))->onQueue('seo');
  }

  // Khi slug thay đổi → tạo redirect (xem ML-12)
  public function updating(ProductTranslation $translation): void
  {
      if ($translation->isDirty('slug')) {
          $oldSlug = $translation->getOriginal('slug');
          $newSlug = $translation->slug;
          $locale  = $translation->locale;

          \App\Models\Redirect::create([
              'from_path'   => "/{$locale}/products/{$oldSlug}",
              'to_path'     => "/{$locale}/products/{$newSlug}",
              'status_code' => 301,
              'locale'      => $locale,
          ]);
      }
  }

Đăng ký observers trong AppServiceProvider::boot():
  ProductTranslation::observe(ProductTranslationObserver::class);
  CategoryTranslation::observe(CategoryTranslationObserver::class);
  BlogPostTranslation::observe(BlogPostTranslationObserver::class);
  BlogCategoryTranslation::observe(BlogCategoryTranslationObserver::class);

Verify:
  php artisan horizon  (phải đang chạy)
  php artisan tinker
  → $p = Product::first()
  → $p->translations()->updateOrCreate(['locale' => 'vi'], ['name' => 'Test', 'slug' => 'test'])
  → Kiểm tra Horizon dashboard: job SyncSitemapEntry đã chạy
```

---

## ML-09 — Jobs: Sync Jobs — Multilingual

```
Mục tiêu: Tất cả sync jobs nhận thêm $locale parameter.

File sửa: app/Jobs/Seo/SyncJsonldSchema.php
  Constructor: public function __construct(
      private readonly Model $model,
      private readonly string $locale
  ) {}

  handle():
  - Gọi JsonldService::buildProductSchema($model, $this->locale) (hoặc build* tương ứng)
  - Lấy template từ jsonld_templates theo model_type
  - Upsert vào jsonld_schemas với (model_type, model_id, locale)
  - is_auto_generated = true (Observer fill, không ghi đè nếu admin đã edit thủ công)
    → Check: if (!$schema->exists || $schema->is_auto_generated) { upsert }

File sửa: app/Jobs/Seo/SyncSitemapEntry.php
  Constructor: thêm string $locale
  handle():
  - Tìm sitemap_index_id theo model_type + locale
    → SitemapIndex::where('model_type', morphAlias($model))->where('locale', $this->locale)->first()
  - Lấy translation = $model->translation($this->locale)
  - if (!$translation) → xóa entry nếu tồn tại, return  ← KHÔNG thêm URL chưa có translation
  - URL = route('{model}.show', ['locale' => $this->locale, 'slug' => $translation->slug])
  - Upsert sitemap_entries: (sitemap_index_id, url, locale)
  - Thêm alternate_urls (jsonb): ['vi' => '...vi url...', 'en' => '...en url...']
    → Dùng cho hreflang xlinks trong XML

File sửa: app/Jobs/Seo/SyncLlmsEntry.php
  Constructor: thêm string $locale
  handle():
  - Tìm llms_document theo model_type + locale
  - Lấy translation, build entry text theo locale
  - Upsert llms_entries với locale

Verify:
  php artisan tinker
  → dispatch(new SyncSitemapEntry(Product::first(), 'vi'))
  → dispatch(new SyncSitemapEntry(Product::first(), 'en'))
  → Kiểm tra sitemap_entries: có 2 rows cho product này (vi + en)
  → Row en: chỉ tồn tại nếu có ProductTranslation locale='en'
```

---

## ML-10 — Sitemap — 8 Child Sitemaps + Hreflang Xlinks

```
Mục tiêu: Route + Controller render sitemap XML với hreflang xlinks chuẩn.

File sửa: routes/web.php — thêm sitemap routes (ngoài locale group):
  Route::get('sitemap.xml', [\App\Http\Controllers\Web\SitemapController::class, 'index']);
  Route::get('sitemap-{locale}-{type}.xml', [\App\Http\Controllers\Web\SitemapController::class, 'child'])
      ->where(['locale' => 'vi|en', 'type' => 'products|product-categories|blog|blog-categories']);

File sửa: app/Http/Controllers/Web/SitemapController.php
  public function index(): Response
  {
      $indexes = SitemapIndex::all();
      return response()->view('sitemap.index', compact('indexes'))
          ->header('Content-Type', 'application/xml');
  }

  public function child(string $locale, string $type): Response
  {
      $slug  = "{$locale}-" . str_replace('-', '-', $type);
      $index = SitemapIndex::where('slug', $slug)->firstOrFail();
      $entries = $index->entries()->with([])->get();

      return response()->view('sitemap.child', compact('entries', 'locale', 'type'))
          ->header('Content-Type', 'application/xml');
  }

File tạo: resources/views/sitemap/index.blade.php
  <?xml version="1.0" encoding="UTF-8"?>
  <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  @foreach($indexes as $index)
      <sitemap>
          <loc>{{ config('app.url') }}{{ $index->path }}</loc>
          <lastmod>{{ $index->updated_at->toAtomString() }}</lastmod>
      </sitemap>
  @endforeach
  </sitemapindex>

File tạo: resources/views/sitemap/child.blade.php
  <?xml version="1.0" encoding="UTF-8"?>
  <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
          xmlns:xhtml="http://www.w3.org/1999/xhtml">
  @foreach($entries as $entry)
      <url>
          <loc>{{ $entry->url }}</loc>
          @if($entry->alternate_urls)
              @foreach($entry->alternate_urls as $lang => $altUrl)
                  <xhtml:link rel="alternate" hreflang="{{ $lang }}" href="{{ $altUrl }}" />
              @endforeach
              <xhtml:link rel="alternate" hreflang="x-default" href="{{ $entry->alternate_urls['vi'] ?? $entry->url }}" />
          @endif
          <changefreq>{{ $entry->changefreq ?? 'weekly' }}</changefreq>
          <priority>{{ $entry->priority ?? '0.8' }}</priority>
          <lastmod>{{ $entry->updated_at->toAtomString() }}</lastmod>
      </url>
  @endforeach
  </urlset>

Verify:
  curl http://localhost/sitemap.xml             → XML với 8 <sitemap> entries
  curl http://localhost/sitemap-vi-products.xml → XML với <url> + <xhtml:link> hreflang
  → Validate tại: https://www.xml-sitemaps.com/validate-xml-sitemap.html
```

---

## ML-11 — LLMs — `/vi/llms.txt` + `/en/llms.txt`

```
Mục tiêu: Mỗi locale có llms.txt riêng bằng ngôn ngữ đó.

File sửa: app/Services/LlmsGeneratorService.php
  Thêm method generate(string $locale): string
  - Query llms_documents where locale = $locale
  - Query llms_entries where locale = $locale, group by document
  - Build plain text output theo đặc tả llms.txt
  - Cache kết quả: Redis key "llms_{$locale}", TTL 1 giờ

File sửa: app/Http/Controllers/Web/LlmsController.php
  public function index(string $locale): Response
  {
      if (!in_array($locale, config('app.supported_locales'), true)) {
          abort(404);
      }
      $content = app(LlmsGeneratorService::class)->generate($locale);
      return response($content, 200)->header('Content-Type', 'text/plain; charset=utf-8');
  }

Route đã khai báo ở ML-04 — không cần thêm.

Artisan command sửa: app/Console/Commands/LlmsGenerateCommand.php
  Thay vì generate() 1 lần → loop qua supported_locales:
  foreach (config('app.supported_locales') as $locale) {
      $this->llmsService->generate($locale);
      $this->info("Generated llms.txt for [{$locale}]");
  }

Verify:
  curl http://localhost/vi/llms.txt → plain text tiếng Việt
  curl http://localhost/en/llms.txt → plain text tiếng Anh
  curl http://localhost/llms.txt    → 302 redirect về /vi/llms.txt
```

---

## ML-12 — Redirects — Per Locale Khi Slug Thay Đổi

```
Mục tiêu: Khi admin đổi slug vi hoặc en → tạo redirect đúng locale.
Logic đã có trong ML-08 (ProductTranslationObserver::updating).
Sprint này hoàn thiện RedirectObserver + RedirectCacheService.

File sửa: app/Observers/RedirectObserver.php
  Sau khi Redirect được tạo → invalidate cache:
  public function created(Redirect $redirect): void
  {
      app(RedirectCacheService::class)->invalidate();
  }

File sửa: app/Http/Middleware/HandleRedirects.php
  Sửa matching logic để support locale trong from_path:
  - Lấy from_path: '/' . $request->path()
  - Query: from_path = $path AND (locale IS NULL OR locale = $detectedLocale)
  - $detectedLocale = $request->route('locale') hoặc parse từ path

File tạo: app/Observers/CategoryTranslationObserver.php
  Tương tự ProductTranslationObserver nhưng:
  - from_path: "/{$locale}/categories/{$oldSlug}"
  - to_path:   "/{$locale}/categories/{$newSlug}"

File tạo: app/Observers/BlogPostTranslationObserver.php
  - from_path: "/{$locale}/blog/{$oldSlug}"
  - to_path:   "/{$locale}/blog/{$newSlug}"

Verify:
  php artisan tinker
  → $t = ProductTranslation::where('locale', 'vi')->first()
  → $t->update(['slug' => 'ao-thun-new'])
  → Redirect::where('from_path', 'LIKE', '%ao-thun%')->first()  → phải tồn tại
  curl -I http://localhost/vi/products/ao-thun-cu   → 301 về /vi/products/ao-thun-new
```

---

## ML-13 — Filament — Product + Category Multilingual Form

```
Mục tiêu: Admin có thể nhập translation vi + en trong cùng 1 form.

File sửa: app/Filament/Resources/ProductResource.php → form()
  Thêm Section mới "Đa ngôn ngữ":
  Forms\Components\Tabs::make('Translations')
      ->tabs([
          Forms\Components\Tabs\Tab::make('🇻🇳 Tiếng Việt (vi)')
              ->schema([
                  TextInput::make('translations.vi.name')
                      ->label('Tên sản phẩm (vi)')
                      ->required()
                      ->live(onBlur: true)
                      ->afterStateUpdated(fn($state, Set $set) =>
                          $set('translations.vi.slug', str($state)->slug())),
                  TextInput::make('translations.vi.slug')
                      ->label('Slug (vi)')
                      ->required()
                      ->rules(['unique:product_translations,slug']),
                  Textarea::make('translations.vi.short_description')->label('Mô tả ngắn (vi)'),
                  RichEditor::make('translations.vi.description')->label('Mô tả đầy đủ (vi)'),
                  Grid::make(2)->schema([
                      TextInput::make('translations.vi.price')
                          ->label('Giá (vi)')->numeric()->nullable(),
                      TextInput::make('translations.vi.currency')
                          ->label('Tiền tệ (vi)')->placeholder('VND')->nullable(),
                  ]),
                  TextInput::make('translations.vi.meta_title')->label('Meta title (vi)'),
                  Textarea::make('translations.vi.meta_description')->label('Meta description (vi)'),
              ]),
          Forms\Components\Tabs\Tab::make('🇬🇧 English (en)')
              ->schema([
                  // Tương tự nhưng key 'translations.en.*'
                  TextInput::make('translations.en.name')->label('Product name (en)'),
                  TextInput::make('translations.en.slug')->label('Slug (en)'),
                  Textarea::make('translations.en.short_description'),
                  RichEditor::make('translations.en.description'),
                  Grid::make(2)->schema([
                      TextInput::make('translations.en.price')->numeric()->nullable(),
                      TextInput::make('translations.en.currency')->placeholder('USD')->nullable(),
                  ]),
                  TextInput::make('translations.en.meta_title'),
                  Textarea::make('translations.en.meta_description'),
              ]),
      ])->columnSpanFull(),

Thêm mutateFormDataBeforeFill() để load translations:
  protected function mutateFormDataBeforeFill(array $data): array
  {
      $record = $this->getRecord();
      foreach (config('app.supported_locales') as $locale) {
          $t = $record->translations()->where('locale', $locale)->first();
          if ($t) {
              $data['translations'][$locale] = $t->toArray();
          }
      }
      return $data;
  }

Thêm mutateFormDataBeforeSave() hoặc afterSave() để save translations:
  protected function afterSave(): void
  {
      $record = $this->getRecord();
      $translationsData = $this->data['translations'] ?? [];
      foreach (config('app.supported_locales') as $locale) {
          if (!empty($translationsData[$locale]['name'])) {
              $record->translations()->updateOrCreate(
                  ['locale' => $locale],
                  $translationsData[$locale]
              );
          }
      }
  }

Làm tương tự cho CategoryResource.

Verify:
  Mở /admin/products/create
  → Thấy tab Tiếng Việt + English
  → Nhập data → Save → Kiểm tra product_translations: 2 rows (vi + en)
```

---

## ML-14 — Filament — BlogPost + BlogCategory Multilingual Form

```
Mục tiêu: Tương tự ML-13 nhưng cho Blog resources.

File sửa: app/Filament/Resources/BlogPostResource.php
  Tabs:
  - vi: title, slug (auto-generate từ title), excerpt, body (RichEditor), meta_title, meta_description
  - en: tương tự

File sửa: app/Filament/Resources/BlogCategoryResource.php
  Tabs:
  - vi: name, slug, description, meta_title, meta_description
  - en: tương tự

Lưu ý: BlogPost KHÔNG có price/currency — chỉ title, slug, excerpt, body, meta.

Verify:
  /admin/blog-posts/create → tab vi + en hiển thị đúng
  Save → blog_post_translations có 2 rows
```

---

## ML-15 — Blade Layouts + Locale Switcher Component

```
Mục tiêu: Layout master locale-aware + component chuyển đổi ngôn ngữ.

File sửa: resources/views/layouts/app.blade.php
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <x-seo.head
          :seo-meta="$seoMeta ?? null"
          :current-url="url()->current()"
          :alternate-urls="$alternateUrls ?? []"
          :fallback-title="$fallbackTitle ?? config('app.name')"
          :fallback-description="$fallbackDescription ?? ''"
      />
      <x-seo.jsonld :schemas="$jsonldSchemas ?? []" />
  </head>
  <body>
      <x-ui.locale-switcher :alternate-urls="$alternateUrls ?? []" />
      @yield('content')
  </body>

File tạo: resources/views/components/ui/locale-switcher.blade.php
  @props(['alternateUrls' => []])
  <div class="locale-switcher">
      @foreach(config('app.supported_locales') as $locale)
          @if($locale !== app()->getLocale())
              <a href="{{ $alternateUrls[$locale] ?? route('home', ['locale' => $locale]) }}"
                 lang="{{ $locale }}">
                  {{ strtoupper($locale) }}
              </a>
          @else
              <span class="active" lang="{{ $locale }}">{{ strtoupper($locale) }}</span>
          @endif
      @endforeach
  </div>

File tạo: resources/views/components/seo/hreflang.blade.php
  (standalone — dùng khi cần render riêng ngoài SeoHead)

Verify:
  php artisan view:clear
  Render 1 page → inspect HTML head
  → Thấy locale-switcher với link đúng
```

---

## ML-16 — Controllers — Resolve Slug Từ `_translations` + 302 Fallback

```
Mục tiêu: Controllers resolve đúng translation theo locale. Nếu chưa có → 302.

File tạo: app/Http/Controllers/Web/ProductController.php
  public function show(string $locale, string $slug): Response
  
  {
      $translation = ProductTranslation::where('locale', $locale)
                                       ->where('slug', $slug)
                                       ->with('product')
                                       ->firstOr(fn() => null);

      if (!$translation) {
          // Tìm vi version theo slug (có thể slug en hoặc vi cũ)
          $viTranslation = ProductTranslation::where('locale', config('app.fallback_locale'))
                                             ->where('slug', $slug)
                                             ->first();
          if ($viTranslation) {
              return redirect(
                  route('product.show', ['locale' => config('app.fallback_locale'), 'slug' => $viTranslation->slug]),
                  302
              );
          }
          abort(404);
      }

      $product = $translation->product;
      if (!$product || !$product->is_active) abort(404);

      $alternateUrls = app(SeoService::class)->alternateUrls($product, 'product.show');
      $seoMeta       = $product->seoMeta($locale);
      $jsonldSchemas = [
          app(JsonldService::class)->buildProductSchema($product, $locale),
          app(JsonldService::class)->buildBreadcrumb([
              ['name' => __('common.home', [], $locale), 'url' => route('home', ['locale' => $locale])],
              ['name' => $product->category?->translation($locale)?->name ?? '', 'url' => '...'],
              ['name' => $translation->name, 'url' => url()->current()],
          ]),
      ];

      return view('pages.product.show', compact(
          'product', 'translation', 'alternateUrls', 'seoMeta', 'jsonldSchemas', 'locale'
      ));
  }

Làm tương tự cho:
  CategoryController::show()   → resolve CategoryTranslation
  BlogController::show()       → resolve BlogPostTranslation
  BlogController::category()   → resolve BlogCategoryTranslation
  PageController::show()       → resolve PageTranslation

Verify:
  curl http://localhost/vi/products/ao-thun-test    → 200 (nếu translation tồn tại)
  curl -I http://localhost/en/products/ao-thun-test → 302 về /vi/ (nếu en chưa dịch)
  curl http://localhost/xx/products/anything        → 404 (locale không hợp lệ)
```

---

## ML-17 — Lang Files vi + en

```
Mục tiêu: Strings tĩnh trong Blade dùng __() helper thay vì hardcode.

Files tạo:
  lang/vi/common.php
    return [
        'home'           => 'Trang chủ',
        'search'         => 'Tìm kiếm',
        'add_to_cart'    => 'Thêm vào giỏ',
        'buy_now'        => 'Mua ngay',
        'out_of_stock'   => 'Hết hàng',
        'read_more'      => 'Xem thêm',
        'back'           => 'Quay lại',
        'all_categories' => 'Tất cả danh mục',
        'latest_posts'   => 'Bài viết mới nhất',
        'price'          => 'Giá',
        'sku'            => 'Mã SP',
    ];

  lang/en/common.php
    return [
        'home'           => 'Home',
        'search'         => 'Search',
        'add_to_cart'    => 'Add to Cart',
        'buy_now'        => 'Buy Now',
        'out_of_stock'   => 'Out of Stock',
        'read_more'      => 'Read More',
        'back'           => 'Back',
        'all_categories' => 'All Categories',
        'latest_posts'   => 'Latest Posts',
        'price'          => 'Price',
        'sku'            => 'SKU',
    ];

  lang/vi/product.php + lang/en/product.php
  lang/vi/blog.php    + lang/en/blog.php

Blade usage:
  {{ __('common.add_to_cart') }}       ← auto dùng locale hiện tại
  {{ __('common.home', [], 'en') }}    ← force locale cụ thể

Verify:
  php artisan tinker
  → app()->setLocale('vi'); __('common.add_to_cart')   → 'Thêm vào giỏ'
  → app()->setLocale('en'); __('common.add_to_cart')   → 'Add to Cart'
```

---

## ML-18 — Robots.txt

```
Mục tiêu: Cho phép Googlebot crawl locale paths, block internal routes.

File tạo: public/robots.txt
  User-agent: *

  # Public locale paths
  Allow: /vi/
  Allow: /en/

  # Block internal
  Disallow: /admin/
  Disallow: /horizon/
  Disallow: /api/
  Disallow: /telescope/

  # Block no-locale paths (đều redirect về /vi/ — không tốn crawl budget)
  Disallow: /products/
  Disallow: /categories/
  Disallow: /blog/

  # Block filter/sort (near-duplicate risk)
  Disallow: /*?sort=
  Disallow: /*?filter=
  Disallow: /*?page=

  # Sitemap
  Sitemap: https://YOUR_DOMAIN/sitemap.xml

Lưu ý: Thay YOUR_DOMAIN bằng domain thực khi deploy.
Không đưa robots.txt vào version control nếu chứa domain production —
hoặc dùng .env để generate động qua route.

Verify:
  curl http://localhost/robots.txt → plain text đúng format
  → Validate tại: https://www.google.com/webmasters/tools/robots-testing-tool
```

---

## ML-19 — Feature Tests

```
Mục tiêu: Tất cả route + SEO behavior được test tự động.
Path: tests/Feature/Multilingual/

File tạo: tests/Feature/Multilingual/LocaleRoutingTest.php
  - test_root_redirects_to_locale()
    → GET / → 302 về /vi/ hoặc /en/
  - test_valid_locale_returns_200()
    → GET /vi/ → 200
    → GET /en/ → 200
  - test_invalid_locale_returns_404()
    → GET /xx/ → 404
  - test_no_locale_path_redirects_301()
    → GET /products/test → 301 về /vi/products/test

File tạo: tests/Feature/Multilingual/ProductTranslationTest.php
  - test_product_show_vi_returns_200()
    → Tạo product + vi translation → GET /vi/products/{vi_slug} → 200
  - test_product_show_en_without_translation_redirects_to_vi()
    → Tạo product + vi translation (không có en) → GET /en/products/{vi_slug} → 302 → /vi/
  - test_product_show_en_with_translation_returns_200()
    → Tạo product + vi + en translations → GET /en/products/{en_slug} → 200
  - test_product_show_nonexistent_slug_returns_404()
    → GET /vi/products/nonexistent → 404

File tạo: tests/Feature/Multilingual/SeoTagsTest.php
  - test_canonical_is_self_referencing()
    → GET /vi/products/{slug} → response HTML có <link rel="canonical" href="...vi/products/...">
    → Canonical KHÔNG chứa /en/
  - test_hreflang_appears_on_both_locales()
    → GET /vi/products/{slug} → có hreflang vi + hreflang en
    → GET /en/products/{slug} → có hreflang vi + hreflang en
  - test_x_default_points_to_vi()
    → hreflang x-default href chứa /vi/

File tạo: tests/Feature/Multilingual/SitemapTest.php
  - test_sitemap_index_has_8_children()
    → GET /sitemap.xml → XML có 8 <sitemap> elements
  - test_child_sitemap_has_hreflang_xlinks()
    → GET /sitemap-vi-products.xml → có xhtml:link elements

File tạo: tests/Feature/Multilingual/LlmsTest.php
  - test_vi_llms_returns_vietnamese_content()
    → GET /vi/llms.txt → 200, Content-Type: text/plain
  - test_en_llms_returns_english_content()
    → GET /en/llms.txt → 200
  - test_root_llms_redirects_to_vi()
    → GET /llms.txt → 302 → /vi/llms.txt

Run: php artisan test --filter=Multilingual
→ Tất cả phải pass trước khi deploy.
```

---

## Lưu ý trước khi deploy

```
□ Horizon đang chạy (php artisan horizon) — bắt buộc cho SEO jobs
□ php artisan sitemap:generate → regenerate tất cả 8 sitemaps
□ php artisan llms:generate → regenerate vi + en llms.txt
□ php artisan jsonld:sync → sync JSON-LD cho tất cả models × locale
□ Cập nhật public/robots.txt: thay YOUR_DOMAIN bằng domain thực
□ Google Search Console: submit https://yourdomain.com/sitemap.xml
□ Google Search Console: dùng URL Inspection tool kiểm tra hreflang trên 2-3 sản phẩm
□ php artisan test --filter=Multilingual → tất cả pass
□ ./vendor/bin/pint → pass
□ ./vendor/bin/phpstan analyse → pass
```
