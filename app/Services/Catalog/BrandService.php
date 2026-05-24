<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Repositories\Eloquent\BrandRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class BrandService
{
    private const LIST_CACHE_KEY = 'brands:active_list';

    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {}

    public function getList(): Collection
    {
        return $this->brandRepository->getActiveList();
    }

    public function getBySlug(string $slug): Brand
    {
        $brand = $this->brandRepository->findActiveBySlug($slug);

        abort_if(! $brand, 404, 'Brand not found.');

        return $brand;
    }

    public function getProductsPaginated(Brand $brand, int $perPage = 15): LengthAwarePaginator
    {
        return $this->brandRepository->getProductsPaginated($brand, $perPage);
    }

    public function bustListCache(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
    }
}
