<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Blog\BlogPostDetailResource;
use App\Http\Resources\Api\Blog\BlogPostResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BlogPostService $blogPostService) {}

    /**
     * GET /api/v1/blog
     */
    public function index(Request $request): JsonResponse
    {
        $posts = $this->blogPostService->list(
            filters: $request->only(['category', 'tag', 'sort']),
            perPage: (int) $request->input('per_page', 12),
        );

        return $this->success(
            data: BlogPostResource::collection($posts),
            meta: $this->paginationMeta($posts),
        );
    }

    /**
     * GET /api/v1/blog/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $post = $this->blogPostService->getBySlug($slug);

        return $this->success(data: new BlogPostDetailResource($post));
    }
}
