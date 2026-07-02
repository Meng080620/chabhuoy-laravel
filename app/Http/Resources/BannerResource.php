<?php

namespace App\Http\Resources;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Banner */
class BannerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            // Absolute URL so the SPA (different origin/port) can load it
            // directly. Null when no image has been uploaded yet.
            'image_url' => $this->image_path
                ? url(Storage::disk('public')->url($this->image_path))
                : null,
            'link_url' => $this->link_url,
            'cta_label' => $this->cta_label,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
