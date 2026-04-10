<?php

namespace App\Services\Category;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    /**
     * Redis cache key for the full active category tree.
     * Busted by CategoryObserver::saved() on every create/update.
     */
    public const TREE_CACHE_KEY = 'categories:tree';
    private const TREE_CACHE_TTL = 600; // 10 minutes

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Return all active root-level categories with their active children
     * pre-loaded, ordered by sort_order.
     *
     * The result is cached in Redis for 10 minutes. The CategoryObserver
     * calls bustTreeCache() on every save to keep it fresh.
     */
    public function getTree(): Collection
    {
        return Cache::remember(self::TREE_CACHE_KEY, self::TREE_CACHE_TTL, function (): Collection {
            return Category::with(['children' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Find an active category by slug, with its paginated active products.
     * Aborts with 404 if not found or inactive.
     *
     * Products are paginated (15 per page) ordered by sort_order then name.
     */
    public function getBySlug(string $slug): Category
    {
        /** @var Category $category */
        $category = Category::with('parent')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $category;
    }

    /**
     * Return a paginated list of active products for a category.
     * Kept separate from getBySlug to allow independent per-page control.
     */
    public function getProductsPaginated(Category $category, int $perPage = 15): LengthAwarePaginator
    {
        return $category->products()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Flush the tree cache.
     * Called by CategoryObserver::saved() and CategoryObserver::deleted().
     */
    public function bustTreeCache(): void
    {
        Cache::forget(self::TREE_CACHE_KEY);
    }
}
