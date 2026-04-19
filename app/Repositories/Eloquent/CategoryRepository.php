<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository extends BaseRepository
{
    protected function model(): string
    {
        return Category::class;
    }

    // ── Tree ──────────────────────────────────────────────────────────────────

    /**
     * All active root categories with their active children, ordered by sort_order.
     * Result is cached by CategoryService — this method only does the DB query.
     */
    public function getActiveTree(): Collection
    {
        return $this->query()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * Single active category by slug with parent loaded.
     */
    public function findActiveBySlug(string $slug): ?Category
    {
        /** @var Category|null */
        return $this->query()
            ->with('parent')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    // ── Products ──────────────────────────────────────────────────────────────

    /**
     * Paginated active products for a category.
     */
    public function getProductsPaginated(Category $category, int $perPage = 15): LengthAwarePaginator
    {
        return $category->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate($perPage);
    }
}
