<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The vendor-facing earnings summary: what's owed to them now, what's already
 * been paid, and the recent payout history. Expects the wrapped Vendor to carry
 * a `total_paid_out` sum (loadSum) and a loaded `payouts` relation.
 *
 * @mixin Vendor
 */
class VendorEarningsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            // Accrued but not yet disbursed — the balance an admin can pay out.
            // Already net of commission_rate (deducted at credit-on-delivery time).
            'available_balance' => $this->payout_balance,
            // The platform's current take — informational, so the vendor can see
            // why a delivered line's credit is less than its line_total.
            'commission_rate' => $this->commission_rate,
            // Lifetime completed disbursements. Null (no payouts) reads as 0.00.
            'total_paid_out' => bcadd((string) ($this->total_paid_out ?? '0'), '0', 2),
            'recent_payouts' => PayoutResource::collection($this->payouts),
        ];
    }
}
