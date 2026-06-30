<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer row in the admin list: profile plus the two lifetime metrics the
 * moderation table sorts and scans on. `orders_count` / `total_spent` are
 * supplied by the controller's `withCount` / `withSum` aggregates — never a
 * per-row query.
 *
 * @mixin User
 */
class CustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Aggregates default to 0 when the relation wasn't loaded with them.
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_spent' => number_format((float) ($this->total_spent ?? 0), 2, '.', ''),
            'created_at' => $this->created_at,
        ];
    }
}
