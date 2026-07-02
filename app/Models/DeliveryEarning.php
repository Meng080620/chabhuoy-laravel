<?php

namespace App\Models;

use App\Enums\DeliveryEarningStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['delivery_man_id', 'amount', 'status', 'reference', 'processed_at'])]
class DeliveryEarning extends Model
{
    use HasFactory, HasUuid;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => DeliveryEarningStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DeliveryMan, $this> */
    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class);
    }
}
