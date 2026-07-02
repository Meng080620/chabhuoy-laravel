<?php

namespace App\Http\Resources;

use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryMan */
class DeliveryManResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isSelfOrAdmin = $request->user()?->isAdmin() || $request->user()?->deliveryMan?->id === $this->id;

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'vehicle_type' => $this->vehicle_type,
            'status' => $this->status,
            'is_online' => $this->is_online,
            // Money fields are private to the rider/admin, mirroring VendorResource.
            'wallet_balance' => $this->when($isSelfOrAdmin, $this->wallet_balance),
            'cash_in_hand' => $this->when($isSelfOrAdmin, $this->cash_in_hand),
        ];
    }
}
