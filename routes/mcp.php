<?php

use App\Http\Controllers\Mcp\AuditController;
use App\Http\Controllers\Mcp\Batch\SeoMetaController  as BatchSeoMetaController;
use App\Http\Controllers\Mcp\Batch\TranslateController as BatchTranslateController;
use App\Http\Controllers\Mcp\EntityListController;
use App\Http\Controllers\Mcp\ReviewQueueController;
use App\Http\Controllers\Mcp\SearchController;
use App\Http\Controllers\Mcp\BlogCategory\ActivateController  as BlogCategoryActivateController;
use App\Http\Controllers\Mcp\BlogCategory\ContextController   as BlogCategoryContextController;
use App\Http\Controllers\Mcp\BlogCategory\ReadinessController as BlogCategoryReadinessController;
use App\Http\Controllers\Mcp\BlogCategory\UpsertController    as BlogCategoryUpsertController;
use App\Http\Controllers\Mcp\BlogPost\ContextController       as BlogPostContextController;
use App\Http\Controllers\Mcp\BlogPost\PublishController       as BlogPostPublishController;
use App\Http\Controllers\Mcp\BlogPost\ReadinessController     as BlogPostReadinessController;
use App\Http\Controllers\Mcp\BlogPost\UpsertController        as BlogPostUpsertController;
use App\Http\Controllers\Mcp\Brand\ActivateController         as BrandActivateController;
use App\Http\Controllers\Mcp\Brand\ContextController          as BrandContextController;
use App\Http\Controllers\Mcp\Brand\ReadinessController        as BrandReadinessController;
use App\Http\Controllers\Mcp\Brand\UpsertController           as BrandUpsertController;
use App\Http\Controllers\Mcp\Manufacturer\ActivateController  as ManufacturerActivateController;
use App\Http\Controllers\Mcp\Manufacturer\ContextController   as ManufacturerContextController;
use App\Http\Controllers\Mcp\Manufacturer\ReadinessController as ManufacturerReadinessController;
use App\Http\Controllers\Mcp\Manufacturer\UpsertController    as ManufacturerUpsertController;
use App\Http\Controllers\Mcp\Category\ActivateController     as CategoryActivateController;
use App\Http\Controllers\Mcp\Category\ContextController      as CategoryContextController;
use App\Http\Controllers\Mcp\Category\ReadinessController    as CategoryReadinessController;
use App\Http\Controllers\Mcp\Category\UpsertController       as CategoryUpsertController;
use App\Http\Controllers\Mcp\Product\ActivateController      as ProductActivateController;
use App\Http\Controllers\Mcp\Product\ContextController    as ProductContextController;
use App\Http\Controllers\Mcp\Product\ReadinessController  as ProductReadinessController;
use App\Http\Controllers\Mcp\Product\UpsertController     as ProductUpsertController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MCP API Routes — /api/v1/mcp/*
|--------------------------------------------------------------------------
| Auth: Bearer token (Sanctum Personal Access Token)
| Scopes: mcp:read | mcp:write | mcp:publish
|
| Rule: fixed-path routes always declared BEFORE wildcards.
*/

Route::prefix('v1/mcp')->middleware(['auth:sanctum'])->group(function () {

    // ── mcp:read — all GET / discovery ────────────────────────────────────────
    Route::middleware('mcp.ability:mcp:read')->group(function () {

        // Sprint 0: Discovery
        Route::get('audit',        AuditController::class);
        Route::get('search',       SearchController::class);
        Route::get('review-queue', ReviewQueueController::class);

        // Sprint 1: Products — read
        Route::get('products/{slug}/context',   ProductContextController::class);
        Route::get('products/{slug}/readiness', ProductReadinessController::class);

        // Sprint 2: Categories — read
        Route::get('categories/{slug}/context',   CategoryContextController::class);
        Route::get('categories/{slug}/readiness', CategoryReadinessController::class);

        // Sprint 3: Blog posts + Blog categories — read
        Route::get('blog-posts/{slug}/context',          BlogPostContextController::class);
        Route::get('blog-posts/{slug}/readiness',        BlogPostReadinessController::class);
        Route::get('blog-categories/{slug}/context',     BlogCategoryContextController::class);
        Route::get('blog-categories/{slug}/readiness',   BlogCategoryReadinessController::class);

        // Sprint 4: Brands + Manufacturers — read
        Route::get('brands/{slug}/context',              BrandContextController::class);
        Route::get('brands/{slug}/readiness',            BrandReadinessController::class);
        Route::get('manufacturers/{slug}/context',       ManufacturerContextController::class);
        Route::get('manufacturers/{slug}/readiness',     ManufacturerReadinessController::class);

        // Generic entity list — MUST be last (wildcard catches everything)
        Route::get('{modelType}', EntityListController::class);
    });

    // ── mcp:write — draft writes ───────────────────────────────────────────────
    Route::middleware('mcp.ability:mcp:write')->group(function () {

        // Sprint 1: Products — upsert (supports ?dry_run=true)
        Route::put('products/{slug}', ProductUpsertController::class);

        // Sprint 2: Categories — upsert
        Route::put('categories/{slug}', CategoryUpsertController::class);

        // Sprint 3: Blog posts + Blog categories — upsert
        Route::put('blog-posts/{slug}',      BlogPostUpsertController::class);
        Route::put('blog-categories/{slug}', BlogCategoryUpsertController::class);

        // Sprint 4: Brands + Manufacturers — upsert
        Route::put('brands/{slug}',        BrandUpsertController::class);
        Route::put('manufacturers/{slug}', ManufacturerUpsertController::class);

        // Sprint 5: Batch operations
        Route::post('batch/seo-meta',  BatchSeoMetaController::class);
        Route::post('batch/translate', BatchTranslateController::class);
    });

    // ── mcp:publish — activate / publish ──────────────────────────────────────
    Route::middleware('mcp.ability:mcp:publish')->group(function () {

        // Sprint 1: Products — activate
        Route::patch('products/{slug}/activate', ProductActivateController::class);

        // Sprint 2: Categories — activate
        Route::patch('categories/{slug}/activate', CategoryActivateController::class);

        // Sprint 3: Blog posts — publish; Blog categories — activate
        Route::patch('blog-posts/{slug}/publish',       BlogPostPublishController::class);
        Route::patch('blog-categories/{slug}/activate', BlogCategoryActivateController::class);

        // Sprint 4: Brands + Manufacturers — activate
        Route::patch('brands/{slug}/activate',        BrandActivateController::class);
        Route::patch('manufacturers/{slug}/activate', ManufacturerActivateController::class);
    });
});
