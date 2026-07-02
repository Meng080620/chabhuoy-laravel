<?php

namespace App\Models;

use App\Enums\BannerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed storefront content. Every visual block on the customer
 * homepage is a Banner row so nothing is hard-coded in the frontend.
 */
#[Fillable([
    'type',
    'title',
    'subtitle',
    'image_path',
    'link_url',
    'cta_label',
    'position',
    'is_active',
])]
class Banner extends Model
{
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => BannerType::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Banner> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Storefront ordering: ascending position, stable by id for ties.
     *
     * @param Builder<Banner> $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
