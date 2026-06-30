<?php

namespace App\Http\Resources;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payout */
class PayoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            // Only loaded for the admin list; the vendor's own earnings view
            // already knows the vendor, so it omits the relation.
            'vendor' => $this->whenLoaded('vendor', fn () => [
                'id' => $this->vendor->uuid,
                'name' => $this->vendor->name,
            ]),
            'amount' => $this->amount,
            'status' => $this->status,
            'reference' => $this->reference,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
