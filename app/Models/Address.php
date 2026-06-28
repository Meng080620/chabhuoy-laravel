<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'label',
    'recipient_name',
    'phone',
    'line1',
    'line2',
    'city',
    'postal_code',
    'country',
    'is_default',
])]
class Address extends Model
{
    use HasFactory, HasUuid;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The fields copied onto an order as an immutable shipping snapshot.
     *
     * @return array<string, string|null>
     */
    public function toOrderSnapshot(): array
    {
        return [
            'ship_recipient_name' => $this->recipient_name,
            'ship_phone' => $this->phone,
            'ship_line1' => $this->line1,
            'ship_line2' => $this->line2,
            'ship_city' => $this->city,
            'ship_postal_code' => $this->postal_code,
            'ship_country' => $this->country,
        ];
    }
}
