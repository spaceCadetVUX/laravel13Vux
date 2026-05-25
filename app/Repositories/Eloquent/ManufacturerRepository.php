<?php

namespace App\Repositories\Eloquent;

use App\Models\Manufacturer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ManufacturerRepository extends BaseRepository
{
    protected function model(): string
    {
        return Manufacturer::class;
    }

    public function getActiveList(): Collection
    {
        return $this->query()
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Manufacturer
    {
        /** @var Manufacturer|null */
        return $this->query()
            ->with(['seoMetas', 'activeSchemas'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    public function getProductsPaginated(Manufacturer $manufacturer, int $perPage = 15): LengthAwarePaginator
    {
        return $manufacturer->products()
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate($perPage);
    }
}
