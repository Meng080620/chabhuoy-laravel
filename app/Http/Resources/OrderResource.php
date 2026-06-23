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
