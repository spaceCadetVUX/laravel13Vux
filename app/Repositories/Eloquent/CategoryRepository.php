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
            ->with([
                'translations',
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'children.translations',
            ])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * Single active category by slug.
     * Eager-loads parent, seoMetas and activeSchemas for the detail API response.
     */
    public function findActiveBySlug(string $slug): ?Category
    {
        /** @var Category|null */
        return $this->query()
            ->with(['parent', 'seoMetas', 'activeSchemas', 'translations'])
            ->where(function ($q) use ($slug): void {
                $q->where('slug', $slug)
                  ->orWhereHas('translations', fn ($t) => $t->where('slug', $slug));
            })
            ->where('is_active', true)
            ->first();
    }

    // ── Products ──────────────────────────────────────────────────────────────

    /**
     * Paginated active products for a category with sorting and filters.
     *
     * @param  array{sort?:string,min_price?:float,max_price?:float,in_stock?:bool,per_page?:int}  $filters
     */
    public function getProductsPaginated(Category $category, array $filters = []): LengthAwarePaginator
    {
        $query = $category->products()
            ->with(['images', 'categories'])
            ->where('is_active', true);

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['in_stock'])) {
            $query->where('stock_quantity', '>', 0);
        }

        match ($filters['sort'] ?? 'name_asc') {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'newest'     => $query->orderByDesc('created_at'),
            default      => $query->orderBy('name'),
        };

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
