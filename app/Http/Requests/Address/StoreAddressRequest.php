<?php

namespace App\Http\Requests\Address;

use App\Enums\AddressLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'        => ['sometimes', new Enum(AddressLabel::class)],
            'full_name'    => ['required', 'string', 'max:100'],
            'phone'        => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:255'],
            'city'         => ['required', 'string', 'max:100'],
            'district'     => ['required', 'string', 'max:100'],
            'ward'         => ['required', 'string', 'max:100'],
            'is_default'   => ['sometimes', 'boolean'],
        ];
    }
}
