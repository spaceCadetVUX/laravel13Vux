<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Cart\CartItemController;
use App\Http\Controllers\Api\V1\Category\CategoryController;
use App\Http\Controllers\Api\V1\Product\ProductController;
use App\Http\Controllers\Api\V1\Product\ProductSearchController;
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
    Route::get('products',          [ProductController::class, 'index']);
    Route::get('products/{slug}',   [ProductController::class, 'show']);
    Route::get('search',            ProductSearchController::class);

    // ── Cart (S48) ────────────────────────────────────────────────────────
    // Guest + auth (X-Session-ID for guests, Bearer token for auth)
    Route::get('cart',                          [CartController::class, 'show']);
    Route::delete('cart',                       [CartController::class, 'clear']);
    Route::post('cart/items',                   [CartItemController::class, 'store']);
    Route::put('cart/items/{cartItem}',         [CartItemController::class, 'update']);
    Route::delete('cart/items/{cartItem}',      [CartItemController::class, 'destroy']);
    Route::middleware('auth:sanctum')->post('cart/merge', [CartController::class, 'merge']);

    // ── Orders (S49–S50) ─────────────────────────────────────────────────
    // Route::middleware('auth:sanctum')->group(...)

    // ── Blog (S51–S52) ────────────────────────────────────────────────────
    // Route::get('blog', ...)

});
