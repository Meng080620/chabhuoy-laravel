<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'name', 'vehicle_type', 'status', 'wallet_balance', 'cash_in_hand',
    'is_online', 'last_lat', 'last_lng', 'fcm_token',
])]
class DeliveryMan extends Model
{
    use HasFactory, HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:2',
            'cash_in_hand' => 'decimal:2',
            'is_online' => 'boolean',
            'last_lat' => 'decimal:7',
            'last_lng' => 'decimal:7',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<DeliveryAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    /** @return HasMany<DeliveryEarning, $this> */
    public function earnings(): HasMany
    {
        return $this->hasMany(DeliveryEarning::class);
    }

    /** @return HasMany<DeliveryCashSettlement, $this> */
    public function cashSettlements(): HasMany
    {
        return $this->hasMany(DeliveryCashSettlement::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
