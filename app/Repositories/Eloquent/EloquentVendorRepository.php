<?php

namespace App\Repositories\Eloquent;

use App\Models\Vendor;
use App\Repositories\Contracts\VendorRepositoryInterface;

class EloquentVendorRepository implements VendorRepositoryInterface
{
    public function find(int $id): ?Vendor
    {
        return Vendor::find($id);
    }

    public function updateStatus(Vendor $vendor, string $status): Vendor
    {
        $vendor->update(['status' => $status]);

        return $vendor->refresh();
    }

    public function creditPayout(Vendor $vendor, string $amount): void
    {
        // Atomic column-level increment avoids a read-modify-write race when
        // multiple orders settle for the same vendor concurrently.
        $vendor->increment('payout_balance', $amount);
    }

    public function resetPayoutBalance(Vendor $vendor): void
    {
        $vendor->update(['payout_balance' => 0]);
    }
}
