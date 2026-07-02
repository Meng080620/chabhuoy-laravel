<?php

namespace App\Http\Resources;

use App\Models\BrandStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin BrandStore */
class BrandStoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'caption' => $this->caption,
            // Absolute URL so the SPA can load it directly; null until uploaded.
            'logo_url' => $this->logo_path
                ? url(Storage::disk('public')->url($this->logo_path))
                : null,
            'link_url' => $this->link_url,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
