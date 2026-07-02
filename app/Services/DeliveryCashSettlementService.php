<?php

namespace App\Services;

use App\Exceptions\CashSettlementExceedsHeldAmountException;
use App\Models\DeliveryCashSettlement;
use App\Models\DeliveryMan;
use App\Repositories\Contracts\DeliveryManRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rider -> platform: cash the rider physically collected on a COD delivery,
 * handed back. Recorded the instant it happens (no async provider to fail),
 * which is why — unlike Payout/DeliveryEarning — this ledger has no status
 * column.
 */
class DeliveryCashSettlementService
{
    public function __construct(
        private readonly DeliveryManRepositoryInterface $deliveryMen,
    ) {}

    /**
     * @throws CashSettlementExceedsHeldAmountException
     */
    public function record(DeliveryMan $deliveryMan, string $amount): DeliveryCashSettlement
    {
        return DB::transaction(function () use ($deliveryMan, $amount): DeliveryCashSettlement {
            $locked = DeliveryMan::whereKey($deliveryMan->id)->lockForUpdate()->firstOrFail();

            if (bccomp($amount, (string) $locked->cash_in_hand, 2) > 0) {
                throw new CashSettlementExceedsHeldAmountException((string) $locked->cash_in_hand);
            }

            $settlement = $locked->cashSettlements()->create([
                'amount' => $amount,
                'settled_at' => now(),
            ]);

            $this->deliveryMen->decrementCashInHand($locked, $amount);

            return $settlement;
        });
    }
}
