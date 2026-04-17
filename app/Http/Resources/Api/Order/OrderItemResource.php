<?php

namespace App\Http\Resources\Api\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_name' => $this->product_name,
            'product_sku'  => $this->product_sku,
            'quantity'     => $this->quantity,
            'unit_price'   => (string) $this->unit_price,
            'subtotal'     => number_format($this->subtotal, 2, '.', ''),
        ];
    }
}
