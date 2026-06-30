<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Moderation is a visibility toggle: an admin can soft-disable a
            // product (e.g. policy violation) or re-enable it. Price/stock are
            // the vendor's to edit, not the moderator's.
            'is_active' => ['required', 'boolean'],
        ];
    }
}
