<?php

use App\Http\Controllers\Web\AboutController;
use App\Http\Controllers\Web\AuthorController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\HealthController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LlmsController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SolutionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Root: detect preferred locale → redirect ─────────────────────────────────
Route::get('/', function () {
    return redirect('/vi/', 301);
});

// ── System: Health Check ─────────────────────────────────────────────────────
Route::get('health', HealthController::class);

// ── SEO: Sitemap XML ─────────────────────────────────────────────────────────
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-static.xml', [SitemapController::class, 'static']);
Route::get('sitemap-{locale}-{type}.xml', [SitemapController::class, 'child'])
    ->where(['locale' => 'vi|en', 'type' => 'products|product-categories|blog|blog-categories']);

// ── SEO: LLMs TXT ────────────────────────────────────────────────────────────
// These use {locale} param in URI — kept as-is (set.locale reads from route param)
Route::middleware('throttle:30,1')->group(function () {
    $localePattern = implode('|', config('app.supported_locales'));

    Route::get('{locale}/llms.txt', [LlmsController::class, 'localized'])
        ->where('locale', $localePattern)
        ->middleware('set.locale');

    Route::get('llms.txt', fn () => redirect('/vi/llms.txt', 302));
    Route::get('llms-full.txt', [LlmsController::class, 'full']);
    Route::get('llms-{slug}.txt', [LlmsController::class, 'scoped']);
});

// ── API Docs: local + staging only ───────────────────────────────────────────
if (app()->isLocal() || app()->environment('staging')) {
    Route::get('docs', fn () => view('scribe.index'));
    Route::get('test-seo-head', fn () => view('test-seo-head'));
}

// ══════════════════════════════════════════════════════════════════════════════
// TIẾNG VIỆT  /vi/*
//
// Hardcoded prefix "vi" (not a route param) → avoids URI collision with /en/*
// Locale is injected into the route as a virtual parameter by SetLocale
// middleware, so controllers can still type-hint `string $locale` normally.
//
// Route names: vi.{name}
// ══════════════════════════════════════════════════════════════════════════════
Route::prefix('vi')
    ->middleware('set.locale:vi')
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])
            ->name('vi.index');

        // ── Giải pháp (static marketing pages) ───────────────────────────────
        Route::get('giai-phap/dali-casambi', [SolutionController::class, 'dali'])
            ->name('vi.dali-casambi');

        Route::get('giai-phap/wireless-casambi', [SolutionController::class, 'wireless'])
            ->name('vi.wireless-casambi');

        Route::get('giai-phap/theo-vai-tro', [SolutionController::class, 'byRole'])
            ->name('vi.solutions-by-role');

        // ── Giới thiệu ────────────────────────────────────────────────────────
        Route::get('gioi-thieu', [AboutController::class, 'show'])
            ->name('vi.about');

        // ── Tìm kiếm autocomplete (trước tim-kiem để tránh slug collision) ───
        Route::get('tim-kiem/goi-y', [ProductController::class, 'autocomplete'])
            ->name('vi.product.autocomplete');

        // ── Tìm kiếm ─────────────────────────────────────────────────────────
        Route::get('tim-kiem', [SearchController::class, 'index'])
            ->name('vi.search');

        // ── Cửa hàng / Danh mục sản phẩm ─────────────────────────────────────
        Route::get('cua-hang', [ProductController::class, 'index'])
            ->name('vi.product.shop');

        Route::get('danh-muc', [CategoryController::class, 'index'])
            ->name('vi.product.category');

        // ── Danh mục + Sản phẩm ───────────────────────────────────────────────
        Route::get('danh-muc/{slug}', [CategoryController::class, 'show'])
            ->name('vi.category.show');

        Route::get('san-pham/{slug}', [ProductController::class, 'show'])
            ->name('vi.product.show');

        // ── Blog ──────────────────────────────────────────────────────────────
        Route::get('bai-viet', [BlogController::class, 'index'])
            ->name('vi.blog.index');

        Route::get('blog/{slug}', [BlogController::class, 'category'])
            ->name('vi.blog.category');

        // Legacy: redirect old /vi/chu-de/{slug} → /vi/blog/{slug}
        Route::get('chu-de/{slug}', fn(string $locale, string $slug) => redirect("/{$locale}/blog/{$slug}", 301));


        // Nested: /vi/bai-viet/{category_slug}/{slug}
        Route::get('bai-viet/{category_slug}/{slug}', [BlogController::class, 'show'])
            ->name('vi.blog.show');

        // Legacy flat URL → 301 to nested (SEO backward compat)
        Route::get('bai-viet/{slug}', function (string $locale, string $slug) {
            $translation = \App\Models\BlogPostTranslation::where('locale', 'vi')
                ->where('slug', $slug)
                ->with(['blogPost.blogCategory.translations'])
                ->first();
            if (! $translation) {
                abort(404);
            }
            $post = $translation->blogPost;
            return redirect(\App\Support\LocaleUrl::forBlogPost($post, 'vi'), 301);
        })->name('vi.blog.show.legacy');

        // ── Tác giả ───────────────────────────────────────────────────────────
        Route::get('tac-gia/{slug}', [AuthorController::class, 'show'])
            ->name('vi.author.show');

        // ── Trang tĩnh — catch-all, phải đặt cuối cùng ───────────────────────
        Route::get('{slug}', [PageController::class, 'show'])
            ->name('vi.page.show');
    });

// ══════════════════════════════════════════════════════════════════════════════
// ENGLISH  /en/*
// Route names: en.{name}
// ══════════════════════════════════════════════════════════════════════════════
Route::prefix('en')
    ->middleware('set.locale:en')
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])
            ->name('en.index');

        // ── Solutions (static marketing pages) ───────────────────────────────
        Route::get('solutions/dali-casambi', [SolutionController::class, 'dali'])
            ->name('en.dali-casambi');

        Route::get('solutions/wireless-casambi', [SolutionController::class, 'wireless'])
            ->name('en.wireless-casambi');

        Route::get('solutions/by-role', [SolutionController::class, 'byRole'])
            ->name('en.solutions-by-role');

        // ── About ─────────────────────────────────────────────────────────────
        Route::get('about', [AboutController::class, 'show'])
            ->name('en.about');

        // ── Search autocomplete (trước search để tránh slug collision) ────────
        Route::get('search/autocomplete', [ProductController::class, 'autocomplete'])
            ->name('en.product.autocomplete');

        // ── Search ────────────────────────────────────────────────────────────
        Route::get('search', [SearchController::class, 'index'])
            ->name('en.search');

        // ── Shop / Category listing ───────────────────────────────────────────
        Route::get('shop', [ProductController::class, 'index'])
            ->name('en.product.shop');

        Route::get('categories', [CategoryController::class, 'index'])
            ->name('en.product.category');

        // ── Categories + Products ─────────────────────────────────────────────
        Route::get('categories/{slug}', [CategoryController::class, 'show'])
            ->name('en.category.show');

        Route::get('products/{slug}', [ProductController::class, 'show'])
            ->name('en.product.show');

        // ── Blog ──────────────────────────────────────────────────────────────
        Route::get('blog', [BlogController::class, 'index'])
            ->name('en.blog.index');

        // Legacy: old /en/blog/category/{slug} → /en/blog/{slug} (301)
        // Must be declared before blog/{category_slug}/{slug} to avoid collision
        Route::get('blog/category/{slug}', fn(string $locale, string $slug) => redirect("/{$locale}/blog/{$slug}", 301));

        // Blog post: /en/blog/{category_slug}/{post_slug}
        Route::get('blog/{category_slug}/{slug}', [BlogController::class, 'show'])
            ->name('en.blog.show');

        // Blog category + legacy flat post redirect: /en/blog/{slug}
        Route::get('blog/{slug}', function (string $locale, string $slug) {
            // Legacy flat post URL → redirect to nested (301, SEO backward compat)
            $translation = \App\Models\BlogPostTranslation::where('locale', 'en')
                ->where('slug', $slug)
                ->with(['blogPost.blogCategory.translations'])
                ->first();
            if ($translation) {
                return redirect(\App\Support\LocaleUrl::forBlogPost($translation->blogPost, 'en'), 301);
            }
            // Serve as blog category page
            return app(\App\Http\Controllers\Web\BlogController::class)->category($locale, $slug);
        })->name('en.blog.category');

        // ── Authors ───────────────────────────────────────────────────────────
        Route::get('authors/{slug}', [AuthorController::class, 'show'])
            ->name('en.author.show');

        // ── Static pages — catch-all, must be last ────────────────────────────
        Route::get('{slug}', [PageController::class, 'show'])
            ->name('en.page.show');
    });

// ── Fallback: no locale prefix → 301 to /vi/ ────────────────────────────────
Route::fallback(function (Request $request) {
    $path = ltrim($request->path(), '/');
    return redirect('/vi/' . $path, 301);
});
