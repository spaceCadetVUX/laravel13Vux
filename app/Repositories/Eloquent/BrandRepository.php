<?php

namespace App\Repositories\Eloquent;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandRepository extends BaseRepository
{
    protected function model(): string
    {
        return Brand::class;
    }

    // ── List ──────────────────────────────────────────────────────────────────

    /**
     * All active brands ordered by sort_order then name, with products count.
     * Result is cached by BrandService — this method only does the DB query.
     */
    public function getActiveList(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    // ── Detail ────────────────────────────────────────────────────────────────

    /**
     * Single active brand by slug.
     * Eager-loads seoMetas and activeSchemas for the detail API response.
     */
    public function findActiveBySlug(string $slug): ?Brand
    {
        /** @var Brand|null */
        return $this->query()
            ->with(['seoMetas', 'activeSchemas'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    // ── Products ──────────────────────────────────────────────────────────────

    /**
     * Paginated active products for a brand, ordered by name.
     */
    public function getProductsPaginated(Brand $brand, int $perPage = 15): LengthAwarePaginator
    {
        return $brand->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate($perPage);
    }
}
