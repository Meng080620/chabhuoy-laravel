<?php

namespace App\Http\Resources;

use App\Models\DeliveryCashSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryCashSettlement */
class DeliveryCashSettlementResource extends JsonResource
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
            'settled_at' => $this->settled_at,
            'created_at' => $this->created_at,
        ];
    }
}
