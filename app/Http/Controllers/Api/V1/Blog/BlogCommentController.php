<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogCommentRequest;
use App\Http\Resources\Api\Blog\BlogCommentResource;
use App\Http\Resources\Traits\ApiResponse;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/blog/{slug}/comments
     * Paginated approved comments for a published post.
     */
    public function index(string $slug, Request $request): JsonResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $comments = $post->comments()
            ->approved()
            ->with('user')
            ->orderBy('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return $this->success(
            data: BlogCommentResource::collection($comments),
            meta: $this->paginationMeta($comments),
        );
    }

    /**
     * POST /api/v1/blog/{slug}/comments  (auth:sanctum)
     * Submit a comment — stored as is_approved=false until admin approves.
     */
    public function store(string $slug, StoreBlogCommentRequest $request): JsonResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $comment = $post->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->validated('body'),
            'is_approved' => false,
        ]);

        return $this->success(
            data: ['id' => $comment->id, 'body' => $comment->body, 'is_approved' => false],
            message: 'Comment submitted and pending approval',
            status: 201,
        );
    }
}
