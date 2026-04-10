<?php

namespace App\Http\Controllers\Api\V1\Category;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Category\CategoryResource;
use App\Http\Resources\Api\Category\CategoryTreeResource;
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
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = $this->categoryService->getBySlug($slug);

        $perPage  = (int) $request->query('per_page', 15);
        $products = $this->categoryService->getProductsPaginated($category, $perPage);

        return $this->success(
            data: [
                'category' => new CategoryResource($category),
                'products' => $products->items(),   // raw items; wrapped by pagination meta
            ],
            meta: $this->paginationMeta($products),
        );
    }
}
