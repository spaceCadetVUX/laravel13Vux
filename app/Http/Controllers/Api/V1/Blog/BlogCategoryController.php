<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Blog\BlogCategoryResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;

class BlogCategoryController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/blog/categories
     * Returns the full tree: root categories with children eager-loaded.
     */
    public function index(): JsonResponse
    {
        $categories = BlogCategory::active()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return $this->success(data: BlogCategoryResource::collection($categories));
    }
}
