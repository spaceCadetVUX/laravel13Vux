<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogCommentRequest;
use App\Http\Resources\Api\Blog\BlogCommentResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Services\Blog\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BlogPostService $blogPostService,
    ) {}

    public function index(string $slug, Request $request): JsonResponse
    {
        $comments = $this->blogPostService->getComments(
            slug: $slug,
            perPage: (int) $request->input('per_page', 20),
        );

        return $this->success(
            data: BlogCommentResource::collection($comments),
            meta: $this->paginationMeta($comments),
        );
    }

    public function store(string $slug, StoreBlogCommentRequest $request): JsonResponse
    {
        $comment = $this->blogPostService->createComment(
            slug: $slug,
            userId: $request->user()->id,
            body: $request->validated('body'),
        );

        return $this->success(
            data: ['id' => $comment->id, 'body' => $comment->body, 'is_approved' => false],
            message: 'Comment submitted and pending approval',
            status: 201,
        );
    }
}
