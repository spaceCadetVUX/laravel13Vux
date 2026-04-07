<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/*
|--------------------------------------------------------------------------
| All routes are prefixed with /api (set in bootstrap/app.php).
| Controllers are added per sprint (S20–S52).
*/

Route::prefix('v1')->group(function () {

    // ── Health check (public) ─────────────────────────────────────────────
    Route::get('ping', fn () => response()->json(['status' => 'ok']));

    // ── Auth (S20–S22) ────────────────────────────────────────────────────
    // Route::prefix('auth')->group(...)

    // ── Catalog (S45–S47) ─────────────────────────────────────────────────
    // Route::get('products', ...)

    // ── Cart & Orders (S48–S50) ───────────────────────────────────────────
    // Route::middleware('auth:sanctum')->group(...)

    // ── Blog (S51–S52) ────────────────────────────────────────────────────
    // Route::get('blog', ...)

});
