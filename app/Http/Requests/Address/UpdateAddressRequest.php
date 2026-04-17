<?php

namespace App\Http\Requests\Address;

use App\Enums\AddressLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'        => ['sometimes', new Enum(AddressLabel::class)],
            'full_name'    => ['sometimes', 'string', 'max:100'],
            'phone'        => ['sometimes', 'string', 'max:20'],
            'address_line' => ['sometimes', 'string', 'max:255'],
            'city'         => ['sometimes', 'string', 'max:100'],
            'district'     => ['sometimes', 'string', 'max:100'],
            'ward'         => ['sometimes', 'string', 'max:100'],
            'is_default'   => ['sometimes', 'boolean'],
        ];
    }
}
