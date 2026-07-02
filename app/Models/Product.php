<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vendor_id',
    'category_id',
    'name',
    'slug',
    'description',
    'price',
    'stock',
    'low_stock_threshold',
    'is_active',
    'image_path',
])]
class Product extends Model
{
    use HasFactory, HasUuid;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isLowOnStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }

    public function hasStockFor(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Limit to products whose vendor is currently active. A suspended vendor's
     * catalogue must drop out of the storefront even though the rows remain.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeFromActiveVendor(Builder $query): void
    {
        $query->whereHas('vendor', fn (Builder $q) => $q->where('status', Vendor::STATUS_ACTIVE));
    }

    /**
     * The storefront visibility rule, in one place so the list query and the
     * single-product guard can't drift apart.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->is_active && $this->vendor->isActive();
    }
}
