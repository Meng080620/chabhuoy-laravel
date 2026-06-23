<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vendor */
class VendorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'status' => $this->status,
            // Balance is private to the vendor/admin; only expose when present.
            'payout_balance' => $this->when(
                $request->user()?->isAdmin() || $request->user()?->vendor?->id === $this->id,
                $this->payout_balance,
            ),
        ];
    }
}
