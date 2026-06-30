<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            // Top-level when null; otherwise must point at a real category. The
            // slug is derived server-side, never accepted from the client.
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
