<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    // ML-16: resolve slug from product_translations + 302 fallback
    public function show(string $locale, string $slug): Response
    {
        return response("Product [{$slug}] — {$locale}", 200);
    }
}
