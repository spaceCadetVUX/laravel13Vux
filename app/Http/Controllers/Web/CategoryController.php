<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    // ML-16: resolve slug from category_translations + 302 fallback
    public function show(string $locale, string $slug): Response
    {
        return response("Category [{$slug}] — {$locale}", 200);
    }
}
