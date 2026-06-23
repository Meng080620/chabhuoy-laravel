<?php

namespace App\Http\Requests\Vendor;

use App\Enums\FulfillmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role + ability are enforced by route middleware; per-order ownership
        // is checked in the controller (404 if the vendor has no line here).
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // A vendor may advance their own lines to shipped or delivered.
            // Cancellation is a separate customer/admin flow.
            'status' => [
                'required',
                Rule::in([
                    FulfillmentStatus::Shipped->value,
                    FulfillmentStatus::Delivered->value,
                ]),
            ],
        ];
    }

    public function fulfillmentStatus(): FulfillmentStatus
    {
        return FulfillmentStatus::from($this->validated('status'));
    }
}
