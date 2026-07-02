<?php

namespace App\Http\Resources;

use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The rider-facing money summary. Two independent directions, never netted
 * automatically: wallet_balance is what the platform owes the rider, cash_in_hand
 * is what the rider owes the platform (COD cash collected but not yet handed
 * back). net_position is informational only — settling either ledger is a
 * separate explicit action.
 *
 * Expects the wrapped DeliveryMan to carry `total_paid_out`/`total_cash_settled`
 * sums (loadSum) and loaded `earnings`/`cashSettlements` relations.
 *
 * @mixin DeliveryMan
 */
class DeliveryManEarningsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'wallet_balance' => $this->wallet_balance,
            'cash_in_hand' => $this->cash_in_hand,
            'net_position' => bcsub((string) $this->wallet_balance, (string) $this->cash_in_hand, 2),
            'total_paid_out' => bcadd((string) ($this->total_paid_out ?? '0'), '0', 2),
            'total_cash_settled' => bcadd((string) ($this->total_cash_settled ?? '0'), '0', 2),
            'recent_payouts' => DeliveryEarningResource::collection($this->earnings),
            'recent_cash_settlements' => DeliveryCashSettlementResource::collection($this->cashSettlements),
        ];
    }
}
