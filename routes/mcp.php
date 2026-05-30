<?php

use App\Http\Controllers\Mcp\AuditController;
use App\Http\Controllers\Mcp\EntityListController;
use App\Http\Controllers\Mcp\ReviewQueueController;
use App\Http\Controllers\Mcp\SearchController;
use App\Http\Controllers\Mcp\Product\ActivateController   as ProductActivateController;
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

        // Generic entity list — MUST be last (wildcard catches everything)
        Route::get('{modelType}', EntityListController::class);
    });

    // ── mcp:write — draft writes ───────────────────────────────────────────────
    Route::middleware('mcp.ability:mcp:write')->group(function () {

        // Sprint 1: Products — upsert (supports ?dry_run=true)
        Route::put('products/{slug}', ProductUpsertController::class);
    });

    // ── mcp:publish — activate / publish ──────────────────────────────────────
    Route::middleware('mcp.ability:mcp:publish')->group(function () {

        // Sprint 1: Products — activate
        Route::patch('products/{slug}/activate', ProductActivateController::class);
    });
});
