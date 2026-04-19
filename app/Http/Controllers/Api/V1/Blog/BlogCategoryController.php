<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Blog\BlogCategoryResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;

class BlogCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BlogPostService $blogPostService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            data: BlogCategoryResource::collection($this->blogPostService->getCategories()),
        );
    }
}
