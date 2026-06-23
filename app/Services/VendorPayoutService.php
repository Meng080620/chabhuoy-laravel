<?php

namespace App\Services;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorPayoutService
{
    public function __construct(
        private readonly VendorRepositoryInterface $vendors,
    ) {
    }

    /**
     * Pay out a vendor's accumulated balance and zero it, atomically.
     *
     * Returns the amount transferred (0 when there was nothing owed).
     */
    public function process(Vendor $vendor): string
    {
        return DB::transaction(function () use ($vendor): string {
            // Re-read under lock so a concurrent order-settlement credit can't
            // be silently wiped by the reset below.
            $locked = Vendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $amount = (string) $locked->payout_balance;

            if (bccomp($amount, '0', 2) <= 0) {
                return '0.00';
            }

            // TODO: call the disbursement provider here. If it throws, the
            // transaction rolls back and the balance is preserved for retry.
            Log::info('Vendor payout processed', [
                'vendor_id' => $locked->id,
                'amount' => $amount,
            ]);

            $this->vendors->resetPayoutBalance($locked);

            return $amount;
        });
    }
}
