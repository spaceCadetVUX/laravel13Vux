<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Blog\BlogTagResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;

class BlogTagController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/blog/tags
     */
    public function index(): JsonResponse
    {
        $tags = BlogTag::orderBy('name')->get();

        return $this->success(data: BlogTagResource::collection($tags));
    }
}
