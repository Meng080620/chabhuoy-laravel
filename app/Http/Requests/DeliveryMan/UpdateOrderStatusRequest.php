<?php

namespace App\Http\Requests\DeliveryMan;

use App\Enums\DeliveryAssignmentStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    DeliveryAssignmentStatus::PickedUp->value,
                    DeliveryAssignmentStatus::Delivered->value,
                    DeliveryAssignmentStatus::Returned->value,
                ]),
            ],
            'otp' => ['nullable', 'string', 'size:6'],
            'proof_photo' => ['nullable', 'image', 'max:4096'], // 4 MB, same cap as banners
        ];
    }

    /**
     * OTP and proof photo are each only required when their config flag is on
     * AND the target is Delivered — runtime-config-dependent rules, not
     * expressible as static sibling-field rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $delivering = $this->input('status') === DeliveryAssignmentStatus::Delivered->value;

            if ($delivering && config('delivery.otp_required') && blank($this->input('otp'))) {
                $validator->errors()->add('otp', 'The otp field is required to confirm delivery.');
            }

            if ($delivering && config('delivery.proof_photo_required') && ! $this->hasFile('proof_photo')) {
                $validator->errors()->add('proof_photo', 'A proof-of-delivery photo is required to confirm delivery.');
            }
        });
    }

    public function status(): DeliveryAssignmentStatus
    {
        return DeliveryAssignmentStatus::from($this->validated('status'));
    }

    public function otp(): ?string
    {
        return $this->validated('otp');
    }
}
