<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class BlogController extends Controller
{
    // ML-16: blog index with locale
    public function index(string $locale): Response
    {
        return response("Blog index — {$locale}", 200);
    }

    // ML-16: resolve slug from blog_category_translations + 302 fallback
    public function category(string $locale, string $slug): Response
    {
        return response("Blog category [{$slug}] — {$locale}", 200);
    }

    // ML-16: resolve slug from blog_post_translations + 302 fallback
    public function show(string $locale, string $slug): Response
    {
        return response("Blog post [{$slug}] — {$locale}", 200);
    }
}
