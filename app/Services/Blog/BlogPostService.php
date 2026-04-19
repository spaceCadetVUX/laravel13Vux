<?php

namespace App\Services\Blog;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Repositories\Eloquent\BlogCategoryRepository;
use App\Repositories\Eloquent\BlogPostRepository;
use App\Repositories\Eloquent\BlogTagRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BlogPostService
{
    public function __construct(
        private readonly BlogPostRepository     $blogPostRepository,
        private readonly BlogCategoryRepository $blogCategoryRepository,
        private readonly BlogTagRepository      $blogTagRepository,
    ) {}

    // ── Posts ─────────────────────────────────────────────────────────────────

    public function list(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->blogPostRepository->paginate($perPage, $filters);
    }

    public function getBySlug(string $slug): BlogPost
    {
        $post = $this->blogPostRepository->findPublishedBySlug($slug);

        abort_if(! $post, 404, 'Blog post not found.');

        return $post;
    }

    // ── Categories ────────────────────────────────────────────────────────────

    public function getCategories(): Collection
    {
        return $this->blogCategoryRepository->getActiveTree();
    }

    // ── Tags ──────────────────────────────────────────────────────────────────

    public function getTags(): Collection
    {
        return $this->blogTagRepository->getAllOrdered();
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    public function getComments(string $slug, int $perPage = 20): LengthAwarePaginator
    {
        $post = $this->blogPostRepository->findPublishedBySlugOrFail($slug);

        return $this->blogPostRepository->getApprovedComments($post, $perPage);
    }

    public function createComment(string $slug, string $userId, string $body): BlogComment
    {
        $post = $this->blogPostRepository->findPublishedBySlugOrFail($slug);

        return $this->blogPostRepository->createComment($post, $userId, $body);
    }
}
