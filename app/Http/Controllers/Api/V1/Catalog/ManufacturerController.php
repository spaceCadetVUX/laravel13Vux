<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Catalog\ManufacturerDetailResource;
use App\Http\Resources\Api\Catalog\ManufacturerResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Catalog\ManufacturerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ManufacturerService $manufacturerService,
    ) {}

    /**
     * GET /api/v1/manufacturers
     * Return all active manufacturers ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        $manufacturers = $this->manufacturerService->getList();

        return $this->success(
            data: ManufacturerResource::collection($manufacturers),
        );
    }

    /**
     * GET /api/v1/manufacturers/{slug}
     * Return a single manufacturer with SEO, JSON-LD, and its paginated active products.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $manufacturer = $this->manufacturerService->getBySlug($slug);

        $perPage  = (int) $request->query('per_page', 15);
        $products = $this->manufacturerService->getProductsPaginated($manufacturer, $perPage);

        return $this->success(
            data: [
                'manufacturer' => new ManufacturerDetailResource($manufacturer),
                'products'     => $products->items(),
            ],
            meta: $this->paginationMeta($products),
        );
    }
}
