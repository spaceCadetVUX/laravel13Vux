<?php

namespace App\Http\Resources\Api\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'product'  => $this->whenLoaded('product', function () {
                /** @var \App\Models\Product $product */
                $product = $this->product;

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'slug'           => $product->slug,
                    'price'          => (string) $product->price,
                    'sale_price'     => $product->sale_price ? (string) $product->sale_price : null,
                    'stock_quantity' => $product->stock_quantity,
                    'thumbnail'      => $product->relationLoaded('thumbnail')
                        ? $product->thumbnail?->url
                        : null,
                ];
            }),
            'quantity' => $this->quantity,
            'subtotal' => number_format($this->subtotal, 2, '.', ''),
        ];
    }
}
