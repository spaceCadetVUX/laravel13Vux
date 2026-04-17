<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BlogPostService
{
    /**
     * Paginated list of published posts.
     *
     * Filters: category (slug), tag (slug), sort (newest|oldest)
     */
    public function list(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        $query = BlogPost::published()
            ->with(['author', 'blogCategory', 'tags'])
            ->orderBy('published_at', $direction);

        if (! empty($filters['category'])) {
            $query->whereHas('blogCategory', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $filters['tag']));
        }

        return $query->paginate($perPage);
    }

    /**
     * Single published post by slug — with all relations + SEO.
     * Throws ModelNotFoundException (→ 404) if not found or not published.
     */
    public function getBySlug(string $slug): BlogPost
    {
        return BlogPost::published()
            ->with(['author', 'blogCategory', 'tags', 'seoMeta', 'activeSchemas'])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
