<?php

namespace App\Http\Resources;

use App\Models\DeliveryAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin DeliveryAssignment */
class DeliveryAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->uuid,
                'status' => $this->order->status->value,
                'total' => $this->order->total,
                'shipping' => $this->order->ship_recipient_name === null ? null : [
                    'recipient_name' => $this->order->ship_recipient_name,
                    'phone' => $this->order->ship_phone,
                    'line1' => $this->order->ship_line1,
                    'line2' => $this->order->ship_line2,
                    'city' => $this->order->ship_city,
                    'postal_code' => $this->order->ship_postal_code,
                    'country' => $this->order->ship_country,
                ],
            ]),
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor->uuid,
                'name' => $this->vendor->name,
            ]),
            'delivery_fee' => $this->delivery_fee,
            'cod_amount' => $this->cod_amount,
            // Only ever visible to the rider it's assigned to — never in the
            // unassigned pool listing, since delivery_man_id is null there.
            'otp' => $this->when(
                $this->delivery_man_id !== null && $request->user()?->deliveryMan?->id === $this->delivery_man_id,
                $this->otp,
            ),
            'proof_photo_url' => $this->when(
                $this->proof_photo_path !== null
                    && $request->user()?->deliveryMan?->id === $this->delivery_man_id,
                fn () => Storage::disk('public')->url($this->proof_photo_path),
            ),
            'accepted_at' => $this->accepted_at,
            'picked_up_at' => $this->picked_up_at,
            'delivered_at' => $this->delivered_at,
        ];
    }
}
