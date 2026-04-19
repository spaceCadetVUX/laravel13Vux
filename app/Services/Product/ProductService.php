<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Repositories\Eloquent\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->productRepository->paginate($perPage, $filters);
    }

    public function getBySlug(string $slug): Product
    {
        $product = $this->productRepository->findActiveBySlug($slug);

        abort_if(! $product, 404, 'Product not found.');

        return $product;
    }
}
