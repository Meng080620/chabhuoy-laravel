<?php

namespace App\Jobs;

use App\Models\Vendor;
use App\Services\VendorPayoutService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVendorPayout implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(public readonly Vendor $vendor)
    {
    }

    public function handle(VendorPayoutService $payouts): void
    {
        $payouts->process($this->vendor);
    }

    /**
     * Keep the unique lock only until the job starts, so a retry or a later
     * payout for the same vendor isn't blocked forever if a worker dies.
     */
    public int $uniqueFor = 3600;

    /**
     * Don't queue two payouts for the same vendor at once (prevents double
     * disbursement when an event fires twice).
     */
    public function uniqueId(): string
    {
        return 'vendor-payout-'.$this->vendor->id;
    }
}
