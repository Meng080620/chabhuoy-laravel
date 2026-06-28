<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_method' => $this->payment_method->value,
            'total' => $this->total,
            'placed_at' => $this->placed_at,
            // The customer behind the order — only when eager-loaded (admin
            // listing). Customers/vendors never load this relation.
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            // Immutable shipping snapshot captured at checkout (null on legacy rows).
            'shipping' => $this->ship_recipient_name === null ? null : [
                'recipient_name' => $this->ship_recipient_name,
                'phone' => $this->ship_phone,
                'line1' => $this->ship_line1,
                'line2' => $this->ship_line2,
                'city' => $this->ship_city,
                'postal_code' => $this->ship_postal_code,
                'country' => $this->ship_country,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'status' => $item->status->value,
            ])),
        ];
    }
}
