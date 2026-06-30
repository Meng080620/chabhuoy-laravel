<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'payment_method',
    'total',
    'placed_at',
    'ship_recipient_name',
    'ship_phone',
    'ship_line1',
    'ship_line2',
    'ship_city',
    'ship_postal_code',
    'ship_country',
])]
class Order extends Model
{
    use HasFactory, HasUuid;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Distinct vendors that have items in this order — used when fanning
     * out payouts and per-vendor notifications.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function vendorLines(): HasMany
    {
        return $this->items();
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Per-vendor parcels for this order — one shipment per vendor (see
     * {@see Shipment}). A customer following a multi-vendor order tracks each.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
