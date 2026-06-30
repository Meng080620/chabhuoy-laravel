<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VendorEarningsResource;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    /**
     * The authed vendor's money summary: balance owed, lifetime paid out, and
     * the last 10 payouts. Everything is scoped to this vendor — there is no id
     * in the route, so a vendor can only ever read their own earnings.
     */
    public function show(Request $request): VendorEarningsResource
    {
        $vendor = $request->user()->vendor;

        // Both the running total and the history come off the vendor in two
        // aggregate queries — no per-payout N+1 regardless of history size.
        $vendor->loadSum(
            ['payouts as total_paid_out' => fn ($q) => $q->where('status', PayoutStatus::Completed)],
            'amount',
        )->load(['payouts' => fn ($q) => $q->latest()->limit(10)]);

        return VendorEarningsResource::make($vendor);
    }
}
