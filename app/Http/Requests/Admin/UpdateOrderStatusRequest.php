<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Forward transitions (shipped/delivered) are vendor-driven via the
            // fulfillment flow. The only order-level action an admin takes here
            // is cancellation; the legality of *that* transition (e.g. a
            // delivered order can't be cancelled) is enforced in OrderService.
            'status' => [
                'required',
                Rule::in([OrderStatus::Cancelled->value]),
            ],
        ];
    }
}
