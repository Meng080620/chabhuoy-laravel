<?php

namespace App\Http\Controllers\Api\DeliveryMan;

use App\Enums\DeliveryEarningStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryManEarningsResource;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    /**
     * The authed rider's money summary: wallet owed, cash held, lifetime paid
     * out/settled, and recent history. Scoped entirely to this rider — no id
     * in the route.
     */
    public function show(Request $request): DeliveryManEarningsResource
    {
        $rider = $request->user()->deliveryMan;

        $rider->loadSum(
            ['earnings as total_paid_out' => fn ($q) => $q->where('status', DeliveryEarningStatus::Completed)],
            'amount',
        )->loadSum('cashSettlements as total_cash_settled', 'amount')
            ->load([
                'earnings' => fn ($q) => $q->latest()->limit(10),
                'cashSettlements' => fn ($q) => $q->latest()->limit(10),
            ]);

        return DeliveryManEarningsResource::make($rider);
    }
}
