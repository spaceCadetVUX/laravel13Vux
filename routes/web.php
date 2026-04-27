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
// sitemap-{name}.xml — name is now e.g. 'vi-products', 'en-blog'
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{name}.xml', [SitemapController::class, 'child']);

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

// ── Locale group: /{locale}/* ─────────────────────────────────────────────────
// web middleware already applied globally via bootstrap/app.php withRouting
// set.locale resolves locale from route param and sets app()->getLocale()
Route::prefix('{locale}')
    ->where(['locale' => implode('|', config('app.supported_locales'))])
    ->middleware('set.locale')
    ->group(function () {

        // Home
        Route::get('/', HomeController::class . '@index')->name('home');

        // Catalog — product categories
        Route::get('categories/{slug}', [CategoryController::class, 'show'])
            ->name('category.show');

        // Catalog — products
        Route::get('products/{slug}', [ProductController::class, 'show'])
            ->name('product.show');

        // Search
        Route::get('search', [SearchController::class, 'index'])
            ->name('search');

        // Blog — index
        Route::get('blog', [BlogController::class, 'index'])
            ->name('blog.index');

        // Blog — category (MUST be before blog/{slug} to avoid slug collision)
        Route::get('blog/categories/{slug}', [BlogController::class, 'category'])
            ->name('blog.category');

        // Blog — post detail
        Route::get('blog/{slug}', [BlogController::class, 'show'])
            ->name('blog.show');

        // Static pages — MUST be last in group (catch-all segment)
        Route::get('{slug}', [PageController::class, 'show'])
            ->name('page.show');
    });

// ── Fallback: no locale prefix → 301 to /vi/ ────────────────────────────────
// Handles: /products/abc, /categories/xyz → /vi/products/abc
// 301 = permanent (Google won't re-crawl no-locale URLs again)
Route::fallback(function (Request $request) {
    $path = ltrim($request->path(), '/');
    return redirect('/vi/' . $path, 301);
});
