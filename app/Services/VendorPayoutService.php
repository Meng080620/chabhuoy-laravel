<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VendorPayoutService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
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

            // TODO: call the disbursement provider here. If it throws, the
            // transaction rolls back — the ledger row and the balance reset are
            // both undone, leaving the balance intact for a retry.
            $payout = $locked->payouts()->create([
                'amount' => $amount,
                'status' => PayoutStatus::Completed,
                'processed_at' => now(),
            ]);

            $this->vendors->resetPayoutBalance($locked);

            return $payout;
        });
    }
}
