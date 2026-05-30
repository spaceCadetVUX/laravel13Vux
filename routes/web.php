<?php

use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\HealthController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LlmsController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SitemapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Root: detect preferred locale → redirect ─────────────────────────────────
// 302 (temporary) — browser may vary if Accept-Language changes
Route::get('/', function (Request $request) {
    $preferred = $request->getPreferredLanguage(config('app.supported_locales')) ?? 'vi';
    return redirect("/{$preferred}/", 302);
});

// ── System: Health Check ─────────────────────────────────────────────────────
Route::get('health', HealthController::class);

// ── SEO: Sitemap XML ─────────────────────────────────────────────────────────
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{locale}-{type}.xml', [SitemapController::class, 'child'])
    ->where(['locale' => 'vi|en', 'type' => 'products|product-categories|blog|blog-categories']);

// ── SEO: LLMs TXT ────────────────────────────────────────────────────────────
// Locale-aware routes BEFORE locale group to take priority
Route::middleware('throttle:30,1')->group(function () {
    // Per-locale llms.txt: /vi/llms.txt, /en/llms.txt
    // set.locale applied manually — outside the locale prefix group
    $localePattern = implode('|', config('app.supported_locales'));

    Route::get('{locale}/llms.txt', [LlmsController::class, 'localized'])
        ->where('locale', $localePattern)
        ->middleware('set.locale');

    // Root llms.txt → redirect to vi (302 = temporary, will be real when translated)
    Route::get('llms.txt', fn () => redirect('/vi/llms.txt', 302));

    // Legacy scoped routes (kept for backward compat — ML-11 will clean up)
    Route::get('llms-full.txt', [LlmsController::class, 'full']);
    Route::get('llms-{slug}.txt', [LlmsController::class, 'scoped']);
});

// ── API Docs: local + staging only ───────────────────────────────────────────
if (app()->isLocal() || app()->environment('staging')) {
    Route::get('docs', fn () => view('scribe.index'));
    Route::get('test-seo-head', fn () => view('test-seo-head'));
}

// ── vi group: Vietnamese URL paths ──────────────────────────────────────────
// set.locale reads {locale} param → sets app()->setLocale('vi')
Route::prefix('{locale}')
    ->where(['locale' => 'vi'])
    ->middleware('set.locale')
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('danh-muc/{slug}', [CategoryController::class, 'show'])
            ->name('category.show');

        Route::get('san-pham/{slug}', [ProductController::class, 'show'])
            ->name('product.show');

        Route::get('tim-kiem', [SearchController::class, 'index'])
            ->name('search');

        Route::get('bai-viet', [BlogController::class, 'index'])
            ->name('blog.index');

        // chu-de MUST be before bai-viet/{slug} — no collision risk since different prefix
        Route::get('chu-de/{slug}', [BlogController::class, 'category'])
            ->name('blog.category');

        Route::get('bai-viet/{slug}', [BlogController::class, 'show'])
            ->name('blog.show');

        // Static pages — catch-all, must be last
        Route::get('{slug}', [PageController::class, 'show'])
            ->name('page.show');
    });

// ── en group: English URL paths ──────────────────────────────────────────────
Route::prefix('{locale}')
    ->where(['locale' => 'en'])
    ->middleware('set.locale')
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home.en');

        Route::get('categories/{slug}', [CategoryController::class, 'show'])
            ->name('category.show.en');

        Route::get('products/{slug}', [ProductController::class, 'show'])
            ->name('product.show.en');

        Route::get('search', [SearchController::class, 'index'])
            ->name('search.en');

        Route::get('blog', [BlogController::class, 'index'])
            ->name('blog.index.en');

        // blog/category MUST be before blog/{slug} to avoid slug collision
        Route::get('blog/category/{slug}', [BlogController::class, 'category'])
            ->name('blog.category.en');

        Route::get('blog/{slug}', [BlogController::class, 'show'])
            ->name('blog.show.en');

        // Static pages — catch-all, must be last
        Route::get('{slug}', [PageController::class, 'show'])
            ->name('page.show.en');
    });

// ── Fallback: no locale prefix → 301 to /vi/ ────────────────────────────────
// Handles: /products/abc, /categories/xyz → /vi/products/abc
// 301 = permanent (Google won't re-crawl no-locale URLs again)
Route::fallback(function (Request $request) {
    $path = ltrim($request->path(), '/');
    return redirect('/vi/' . $path, 301);
});
