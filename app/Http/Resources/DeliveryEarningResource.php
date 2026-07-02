<?php

namespace App\Http\Resources;

use App\Models\DeliveryEarning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryEarning */
class DeliveryEarningResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'delivery_man' => $this->whenLoaded('deliveryMan', fn () => [
                'id' => $this->deliveryMan->uuid,
                'name' => $this->deliveryMan->name,
            ]),
            'amount' => $this->amount,
            'status' => $this->status,
            'reference' => $this->reference,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
