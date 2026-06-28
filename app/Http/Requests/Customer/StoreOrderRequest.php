<?php

namespace App\Http\Requests\Customer;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Order::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            // Ownership is enforced in the rule itself: the id must exist AND
            // belong to the caller. A valid-but-someone-else's id fails as 422,
            // not 404, so the existence of other users' addresses isn't probed.
            'address_id' => [
                'required',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }
}
