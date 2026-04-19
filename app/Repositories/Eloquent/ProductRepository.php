<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository
{
    protected function model(): string
    {
        return Product::class;
    }

    // ── List ──────────────────────────────────────────────────────────────────

    /**
     * Paginated active product list with filters.
     *
     * Supported filters:
     *   category   string  — filter by category slug
     *   sort       string  — price_asc | price_desc | name_asc | name_desc | newest
     *   min_price  numeric — price >=
     *   max_price  numeric — price <=
     *   in_stock   bool    — stock_quantity > 0 only
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = $this->query()
            ->with($with ?: ['categories', 'thumbnail'])
            ->where('is_active', true);

        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $filters['category']));
        }

        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        if (! empty($filters['in_stock'])) {
            $query->where('stock_quantity', '>', 0);
        }

        match ($filters['sort'] ?? null) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc'   => $query->orderBy('name', 'asc'),
            'name_desc'  => $query->orderBy('name', 'desc'),
            'newest'     => $query->orderBy('created_at', 'desc'),
            default      => $query->orderBy('name', 'asc'),
        };

        return $query->paginate($perPage);
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * Single active product by slug with all detail relations.
     */
    public function findActiveBySlug(string $slug): ?Product
    {
        /** @var Product|null */
        return $this->query()
            ->with(['categories', 'images', 'videos', 'seoMeta', 'activeSchemas'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }
}
