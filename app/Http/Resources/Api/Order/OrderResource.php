<?php

namespace App\Http\Resources\Api\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status->value,
            'payment_status'   => $this->payment_status->value,
            'total_amount'     => (string) $this->total_amount,
            'shipping_address' => $this->shipping_address,
            'note'             => $this->note,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
