<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Blog\BlogCategoryDetailResource;
use App\Http\Resources\Api\Blog\BlogCategoryResource;
use App\Http\Resources\Api\Blog\BlogPostResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Blog\BlogCategoryService;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BlogPostService    $blogPostService,
        private readonly BlogCategoryService $blogCategoryService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            data: BlogCategoryResource::collection($this->blogPostService->getCategories()),
        );
    }

    /**
     * GET /api/v1/blog/categories/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = $this->blogCategoryService->getBySlug($slug);

        $perPage  = (int) $request->query('per_page', 12);
        $filters  = array_filter([
            'sort'     => $request->query('sort'),
            'per_page' => $perPage,
        ], fn ($v) => $v !== null);

        $posts = $this->blogCategoryService->getPostsPaginated($category, $filters);

        $data           = (new BlogCategoryDetailResource($category))->resolve();
        $data['posts']  = BlogPostResource::collection($posts->items())->resolve();

        return $this->success(
            data: $data,
            meta: $this->paginationMeta($posts),
        );
    }
}
