<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Category\CategoryDetailResource;
use App\Http\Resources\Api\Category\CategoryResource;
use App\Http\Resources\Api\Category\CategoryTreeResource;
use App\Http\Resources\Api\Product\ProductResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Category\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * GET /api/v1/categories
     * Return the full active category tree (root categories + nested children).
     */
    public function index(): JsonResponse
    {
        $tree = $this->categoryService->getTree();

        return $this->success(
            data: CategoryTreeResource::collection($tree),
        );
    }

    /**
     * GET /api/v1/categories/{slug}
     * Return a single category with its paginated active products.
     * Query params: page, per_page, sort (price_asc|price_desc|newest|name_asc), min_price, max_price, in_stock
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = $this->categoryService->getBySlug($slug);

        $filters = array_filter([
            'sort'      => $request->query('sort'),
            'min_price' => $request->query('min_price') !== null ? (float) $request->query('min_price') : null,
            'max_price' => $request->query('max_price') !== null ? (float) $request->query('max_price') : null,
            'in_stock'  => filter_var($request->query('in_stock'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'per_page'  => (int) $request->query('per_page', 15),
        ], fn ($v) => $v !== null);

        $products = $this->categoryService->getProductsPaginated($category, $filters);

        $data             = (new CategoryDetailResource($category))->resolve();
        $data['products'] = ProductResource::collection($products->items())->resolve();

        return $this->success(
            data: $data,
            meta: $this->paginationMeta($products),
        );
    }
}
