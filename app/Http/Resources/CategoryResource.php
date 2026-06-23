<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            // Integer id is exposed deliberately: it's the value clients pass to
            // GET /products?category_id=. Categories are public taxonomy, not the
            // sensitive business ids that HasUuid hides. `slug` is the URL handle.
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
