<?php

namespace App\Services;

use App\Enums\DeliveryEarningStatus;
use App\Models\DeliveryEarning;
use App\Models\DeliveryMan;
use App\Repositories\Contracts\DeliveryManRepositoryInterface;
use App\Services\Contracts\DisbursementProvider;
use Illuminate\Support\Facades\DB;

/**
 * Platform -> rider disbursement. A line-for-line mirror of
 * VendorPayoutService for the rider wallet_balance instead of a vendor's
 * payout_balance — kept as its own service/ledger so the two domains never
 * get conflated even though today's provider is a stub for both.
 */
class DeliveryEarningService
{
    public function __construct(
        private readonly DeliveryManRepositoryInterface $deliveryMen,
        private readonly DisbursementProvider $disbursements,
    ) {}

    public function process(DeliveryMan $deliveryMan): ?DeliveryEarning
    {
        return DB::transaction(function () use ($deliveryMan): ?DeliveryEarning {
            $locked = DeliveryMan::whereKey($deliveryMan->id)->lockForUpdate()->firstOrFail();

            $amount = (string) $locked->wallet_balance;

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            // Row first, so the disbursement carries this earning's stable id.
            // A provider failure rolls the whole transaction back — row and
            // wallet reset both undone, leaving the balance intact for a retry.
            $earning = $locked->earnings()->create([
                'amount' => $amount,
                'status' => DeliveryEarningStatus::Completed,
                'processed_at' => now(),
            ]);

            $reference = $this->disbursements->send($amount, 'earning_'.$earning->uuid);
            $earning->update(['reference' => $reference]);

            $this->deliveryMen->resetWalletBalance($locked);

            return $earning;
        });
    }
}
