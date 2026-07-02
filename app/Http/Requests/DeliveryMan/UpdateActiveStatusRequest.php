<?php

namespace App\Http\Requests\DeliveryMan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActiveStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_online' => ['required', 'boolean'],
        ];
    }
}
