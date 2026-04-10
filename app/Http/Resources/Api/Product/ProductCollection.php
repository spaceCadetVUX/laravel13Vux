<?php

namespace App\Http\Resources\Api\Product;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    /**
     * Each item in the collection is wrapped with ProductResource.
     * Pagination meta is added by the controller via ApiResponse::paginationMeta().
     */
    public $collects = ProductResource::class;
}
