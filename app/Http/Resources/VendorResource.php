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
            // Balance and take rate are private to the vendor/admin — a
            // competitor should never see another vendor's negotiated cut.
            'payout_balance' => $this->when(
                $request->user()?->isAdmin() || $request->user()?->vendor?->id === $this->id,
                $this->payout_balance,
            ),
            'commission_rate' => $this->when(
                $request->user()?->isAdmin() || $request->user()?->vendor?->id === $this->id,
                $this->commission_rate,
            ),
        ];
    }
}
