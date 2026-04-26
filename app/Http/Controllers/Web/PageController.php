<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class PageController extends Controller
{
    // ML-16: resolve from page_translations by locale + slug
    public function show(string $locale, string $slug): Response
    {
        return response("Page [{$slug}] — {$locale}", 200);
    }
}
