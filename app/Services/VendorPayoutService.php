<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Services\Contracts\DisbursementProvider;
use Illuminate\Support\Facades\DB;

class VendorPayoutService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
        private readonly DisbursementProvider $disbursements,
    ) {}

    /**
     * Pay out a vendor's accumulated balance and zero it, atomically.
     *
     * Records the disbursement as a Payout ledger row *before* draining the
     * balance, all in one transaction — so settlement is always auditable and a
     * provider failure rolls back both the row and the reset, preserving the
     * balance for retry.
     *
     * Returns the Payout created, or null when there was nothing owed.
     */
    public function process(Vendor $vendor): ?Payout
    {
        return DB::transaction(function () use ($vendor): ?Payout {
            // Re-read under lock so a concurrent order-settlement credit can't
            // be silently wiped by the reset below.
            $locked = Vendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $amount = (string) $locked->payout_balance;

            if (bccomp($amount, '0', 2) <= 0) {
                return null;
            }

            // Record the row first so the disbursement carries this payout's
            // stable id as its reference. If the provider throws, the whole
            // transaction rolls back — row and balance reset both undone,
            // leaving the balance intact for a retry.
            $payout = $locked->payouts()->create([
                'amount' => $amount,
                'status' => PayoutStatus::Completed,
                'processed_at' => now(),
            ]);

            $reference = $this->disbursements->send($amount, 'payout_'.$payout->uuid);
            $payout->update(['reference' => $reference]);

            $this->vendors->resetPayoutBalance($locked);

            return $payout;
        });
    }
}
