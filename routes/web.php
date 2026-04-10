<?php

use App\Http\Controllers\Web\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ── SEO: Sitemap XML ─────────────────────────────────────────────────────────
Route::get('sitemap.xml', [SitemapController::class, 'index']);
Route::get('sitemap-{name}.xml', [SitemapController::class, 'child']);
