<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Catalog\BrandDetailResource;
use App\Http\Resources\Api\Catalog\BrandResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Catalog\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BrandService $brandService,
    ) {}

    /**
     * GET /api/v1/brands
     * Return all active brands ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        $brands = $this->brandService->getList();

        return $this->success(
            data: BrandResource::collection($brands),
        );
    }

    /**
     * GET /api/v1/brands/{slug}
     * Return a single brand with SEO, JSON-LD, and its paginated active products.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $brand = $this->brandService->getBySlug($slug);

        $perPage  = (int) $request->query('per_page', 15);
        $products = $this->brandService->getProductsPaginated($brand, $perPage);

        return $this->success(
            data: [
                'brand'    => new BrandDetailResource($brand),
                'products' => $products->items(),
            ],
            meta: $this->paginationMeta($products),
        );
    }
}
