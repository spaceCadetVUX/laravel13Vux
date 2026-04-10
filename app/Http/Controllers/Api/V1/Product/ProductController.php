<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Product\ProductCollection;
use App\Http\Resources\Api\Product\ProductDetailResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProductService $productService,
    ) {}

    /**
     * GET /api/v1/products
     *
     * Query params:
     *   category   — filter by category slug
     *   sort       — price_asc | price_desc | name_asc | name_desc | newest
     *   min_price  — minimum price
     *   max_price  — maximum price
     *   in_stock   — 1 to filter by available stock
     *   per_page   — default 15
     */
    public function index(Request $request): JsonResponse
    {
        $perPage  = (int) $request->query('per_page', 15);
        $products = $this->productService->list($request->query(), $perPage);

        return $this->success(
            data: new ProductCollection($products),
            meta: $this->paginationMeta($products),
        );
    }

    /**
     * GET /api/v1/products/{slug}
     * Full product detail with images, videos, SEO meta, and JSON-LD schemas.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getBySlug($slug);

        return $this->success(
            data: new ProductDetailResource($product),
        );
    }
}
