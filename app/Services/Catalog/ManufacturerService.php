<?php

namespace App\Services\Catalog;

use App\Models\Manufacturer;
use App\Repositories\Eloquent\ManufacturerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ManufacturerService
{
    private const LIST_CACHE_KEY = 'manufacturers:active_list';

    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepository,
    ) {}

    public function getList(): Collection
    {
        return $this->manufacturerRepository->getActiveList();
    }

    public function getBySlug(string $slug): Manufacturer
    {
        $manufacturer = $this->manufacturerRepository->findActiveBySlug($slug);

        abort_if(! $manufacturer, 404, 'Manufacturer not found.');

        return $manufacturer;
    }

    public function getProductsPaginated(Manufacturer $manufacturer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->manufacturerRepository->getProductsPaginated($manufacturer, $perPage);
    }

    public function bustListCache(): void
    {
        Cache::forget(self::LIST_CACHE_KEY);
    }
}
