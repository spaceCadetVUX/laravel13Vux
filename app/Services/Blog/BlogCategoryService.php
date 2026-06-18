<?php

namespace App\Services\Blog;

use App\Models\BlogCategory;
use App\Repositories\Eloquent\BlogCategoryRepository;
use App\Repositories\Eloquent\BlogPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BlogCategoryService
{
    public function __construct(
        private readonly BlogCategoryRepository $blogCategoryRepository,
        private readonly BlogPostRepository     $blogPostRepository,
    ) {}

    public function getBySlug(string $slug): BlogCategory
    {
        $category = $this->blogCategoryRepository->findActiveBySlug($slug);

        abort_if(! $category, 404, 'Blog category not found.');

        return $category;
    }

    public function getPostsPaginated(BlogCategory $category, array $filters = []): LengthAwarePaginator
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $perPage   = (int) ($filters['per_page'] ?? 12);

        return $this->blogPostRepository->paginateByCategory($category, $perPage, $direction);
    }
}
