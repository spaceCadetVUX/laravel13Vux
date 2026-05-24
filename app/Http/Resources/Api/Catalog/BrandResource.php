<?php

namespace App\Http\Resources\Api\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'logo_url'       => $this->logo
                ? asset('storage/' . $this->logo)
                : null,
            'description'    => $this->description,
            'website'        => $this->website,
            'sort_order'     => $this->sort_order,
            'products_count' => $this->when(
                isset($this->products_count),
                $this->products_count,
            ),
        ];
    }
}
