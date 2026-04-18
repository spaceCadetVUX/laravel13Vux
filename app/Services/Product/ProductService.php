<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Paginated list of active products with optional filters.
     *
     * Supported filters (all optional):
     *   category   string  — filter by category slug
     *   sort       string  — price_asc | price_desc | name_asc | name_desc | newest
     *   min_price  numeric — products with price >= value
     *   max_price  numeric — products with price <= value
     *   in_stock   bool    — only products with stock_quantity > 0
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::with(['categories', 'images'])
            ->where('is_active', true);

        // ── Category filter ────────────────────────────────────────────────────
        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $filters['category']));
        }

        // ── Price range filters ────────────────────────────────────────────────
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        // ── In-stock filter ────────────────────────────────────────────────────
        if (! empty($filters['in_stock'])) {
            $query->where('stock_quantity', '>', 0);
        }

        // ── Sorting ────────────────────────────────────────────────────────────
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

    /**
     * Fetch a single active product by slug with all detail relations loaded.
     * Aborts 404 if not found or inactive.
     */
    public function getBySlug(string $slug): Product
    {
        /** @var Product $product */
        $product = Product::with([
            'categories',
            'images',
            'videos',
            'seoMeta',
            'activeSchemas',
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return $product;
    }
}
