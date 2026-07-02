<?php

namespace App\Http\Requests\Admin;

use App\Models\DeliveryMan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryManStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    DeliveryMan::STATUS_PENDING,
                    DeliveryMan::STATUS_ACTIVE,
                    DeliveryMan::STATUS_SUSPENDED,
                ]),
            ],
        ];
    }
}
