<?php

namespace App\Repositories\Eloquent;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BlogPostRepository extends BaseRepository
{
    protected function model(): string
    {
        return BlogPost::class;
    }

    // ── List ──────────────────────────────────────────────────────────────────

    /**
     * Paginated published posts.
     *
     * Filters: category (slug), tag (slug), sort (newest|oldest)
     */
    public function paginate(int $perPage = 12, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        $query = $this->query()
            ->published()
            ->with($with ?: ['author', 'blogCategory', 'tags'])
            ->orderBy('published_at', $direction);

        if (! empty($filters['category'])) {
            $query->whereHas('blogCategory', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $filters['tag']));
        }

        return $query->paginate($perPage);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * Single published post by slug with all detail relations.
     */
    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        /** @var BlogPost|null */
        return $this->query()
            ->published()
            ->with(['author', 'blogCategory', 'tags', 'seoMetas', 'activeSchemas'])
            ->where('slug', $slug)
            ->first();
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    /**
     * Published post by slug — lightweight, no heavy relations.
     * Used by comment endpoints that only need the post to exist.
     */
    public function findPublishedBySlugOrFail(string $slug): BlogPost
    {
        $post = $this->query()
            ->published()
            ->where('slug', $slug)
            ->first();

        abort_if(! $post, 404, 'Blog post not found.');

        return $post;
    }

    /**
     * Paginated approved comments for a post.
     */
    public function getApprovedComments(BlogPost $post, int $perPage = 20): LengthAwarePaginator
    {
        return $post->comments()
            ->approved()
            ->with('user')
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a pending comment on a post.
     */
    public function createComment(BlogPost $post, string $userId, string $body): BlogComment
    {
        return $post->comments()->create([
            'user_id'     => $userId,
            'body'        => $body,
            'is_approved' => false,
        ]);
    }
}
