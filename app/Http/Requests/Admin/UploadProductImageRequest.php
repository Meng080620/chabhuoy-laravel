<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The upload itself; the stored path is derived server-side. Required
            // here (unlike the banner's optional image) — this endpoint's whole
            // job is to set the image. 4 MB cap, same as banners.
            'image' => ['required', 'image', 'max:4096'],
        ];
    }
}
