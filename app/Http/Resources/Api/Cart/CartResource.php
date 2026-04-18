<?php

namespace App\Http\Resources\Api\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'expires_at' => $this->expires_at->toIso8601String(),
            'items'      => CartItemResource::collection($this->whenLoaded('items')),
            'total'      => number_format($this->total, 2, '.', ''),
            'item_count' => $this->item_count,
        ];
    }
}
