<?php

use App\Http\Controllers\Web\HealthController;
use App\Http\Controllers\Web\LlmsController;
use App\Http\Controllers\Web\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── SEO: Sitemap XML ─────────────────────────────────────────────────────────
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{name}.xml', [SitemapController::class, 'child']);

// ── SEO: LLMs TXT (throttled: 30 req/min) ───────────────────────────────────
Route::middleware('throttle:30,1')->group(function () {
    Route::get('llms.txt', [LlmsController::class, 'index']);
    Route::get('llms-full.txt', [LlmsController::class, 'full']);
    Route::get('llms-{slug}.txt', [LlmsController::class, 'scoped']);
});

// ── System: Health Check ─────────────────────────────────────────────────────
Route::get('health', HealthController::class);
