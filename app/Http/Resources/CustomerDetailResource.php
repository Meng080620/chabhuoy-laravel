<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single customer's profile for the admin detail view: the same lifetime
 * metrics as the list row, plus the most recent orders and saved addresses.
 *
 * @mixin User
 */
class CustomerDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_spent' => number_format((float) ($this->total_spent ?? 0), 2, '.', ''),
            'created_at' => $this->created_at,
            // Slim order summaries (no items/customer re-embedded) — newest first.
            'recent_orders' => OrderResource::collection($this->whenLoaded('orders')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
