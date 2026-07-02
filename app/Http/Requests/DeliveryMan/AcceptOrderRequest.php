<?php

namespace App\Http\Requests\DeliveryMan;

use Illuminate\Foundation\Http\FormRequest;

class AcceptOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role + ability are enforced by route middleware; per-assignment
        // eligibility (already taken, offline, over-capacity, ...) is checked
        // in DeliveryAssignmentService.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function lat(): string
    {
        return (string) $this->validated('lat');
    }

    public function lng(): string
    {
        return (string) $this->validated('lng');
    }
}
