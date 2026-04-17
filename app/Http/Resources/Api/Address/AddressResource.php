<?php

namespace App\Http\Resources\Api\Address;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'label'        => $this->label->value,
            'full_name'    => $this->full_name,
            'phone'        => $this->phone,
            'address_line' => $this->address_line,
            'city'         => $this->city,
            'district'     => $this->district,
            'ward'         => $this->ward,
            'is_default'   => $this->is_default,
            'created_at'   => $this->created_at,
        ];
    }
}
