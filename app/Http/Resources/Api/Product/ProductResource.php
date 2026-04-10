<?php

namespace App\Http\Resources\Api\Product;

use App\Http\Resources\Api\Category\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * List-level product representation.
     * Used in category pages, search results, and product listings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'sku'               => $this->sku,
            'short_description' => $this->short_description,
            'price'             => (string) $this->price,
            'sale_price'        => $this->sale_price ? (string) $this->sale_price : null,
            'stock_quantity'    => $this->stock_quantity,
            'is_active'         => $this->is_active,
            'category'          => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'thumbnail'         => $this->whenLoaded(
                'images',
                fn () => $this->images->first()?->url,
            ),
            'created_at'        => $this->created_at->toIso8601String(),
        ];
    }
}
