<?php

namespace App\Http\Requests\DeliveryMan;

use Illuminate\Foundation\Http\FormRequest;

class MakeCollectedCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function amount(): string
    {
        return (string) $this->validated('amount');
    }
}
