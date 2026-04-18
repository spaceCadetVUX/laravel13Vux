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
     * List products
     *
     * Returns a paginated list of active products with optional filters.
     *
     * @unauthenticated
     * @queryParam page int Page number. Default: 1. Example: 1
     * @queryParam per_page int Items per page. Default: 15. Max: 100. Example: 15
     * @queryParam category string Filter by category slug. Example: smartphones
     * @queryParam sort string Sort order: price_asc, price_desc, name_asc, name_desc, newest. Example: newest
     * @queryParam min_price number Minimum price filter. Example: 100000
     * @queryParam max_price number Maximum price filter. Example: 5000000
     * @queryParam in_stock int Set to 1 to return only in-stock products. Example: 1
     * @response 200 scenario="Success" {"success":true,"data":[{"slug":"iphone-15","name":"iPhone 15","price":22990000}],"meta":{"current_page":1,"total":50}}
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
     * Get product detail
     *
     * Returns full product detail including images, videos, SEO meta, and JSON-LD schemas.
     *
     * @unauthenticated
     * @urlParam slug string required The product slug. Example: iphone-15
     * @response 200 scenario="Success" {"success":true,"data":{"slug":"iphone-15","name":"iPhone 15","price":22990000,"seo":{},"jsonld_schemas":[]}}
     * @response 404 scenario="Not found" {"success":false,"message":"Not found"}
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getBySlug($slug);

        return $this->success(
            data: new ProductDetailResource($product),
        );
    }
}
