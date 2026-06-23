<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;

interface VendorRepositoryInterface
{
    public function find(int $id): ?Vendor;

    public function updateStatus(Vendor $vendor, string $status): Vendor;

    /**
     * Atomically add an amount to the vendor's outstanding payout balance.
     */
    public function creditPayout(Vendor $vendor, string $amount): void;

    public function resetPayoutBalance(Vendor $vendor): void;
}
