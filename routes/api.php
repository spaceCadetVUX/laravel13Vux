<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Category\CategoryController;
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
    Route::prefix('auth')->group(function () {

        // Public
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
        Route::post('google',   [SocialAuthController::class, 'google']);  // S21

        // Protected
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);
            Route::put('me',      [AuthController::class, 'update']);   // S22
        });
    });

    // ── Catalog (S45–S47) ─────────────────────────────────────────────────
    Route::get('categories',        [CategoryController::class, 'index']);
    Route::get('categories/{slug}', [CategoryController::class, 'show']);

    // ── Cart & Orders (S48–S50) ───────────────────────────────────────────
    // Route::middleware('auth:sanctum')->group(...)

    // ── Blog (S51–S52) ────────────────────────────────────────────────────
    // Route::get('blog', ...)

});
