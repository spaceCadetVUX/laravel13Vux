<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Repositories\Eloquent\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public const TREE_CACHE_KEY = 'categories:tree';
    private const TREE_CACHE_TTL = 600;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function getTree(): Collection
    {
        return Cache::remember(self::TREE_CACHE_KEY, self::TREE_CACHE_TTL,
            fn () => $this->categoryRepository->getActiveTree()
        );
    }

    public function getBySlug(string $slug): Category
    {
        $category = $this->categoryRepository->findActiveBySlug($slug);

        abort_if(! $category, 404, 'Category not found.');

        return $category;
    }

    public function getProductsPaginated(Category $category, int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->getProductsPaginated($category, $perPage);
    }

    public function bustTreeCache(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }
}
