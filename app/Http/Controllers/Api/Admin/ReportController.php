<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Platform-wide sales summary. Aggregation is pushed into SQL rather than
     * hydrating models — this stays cheap as the orders table grows.
     */
    public function sales(Request $request): JsonResponse
    {
        $paid = Order::where('status', OrderStatus::Paid);

        return response()->json([
            'orders_count' => (clone $paid)->count(),
            'gross_revenue' => (clone $paid)->sum('total'),
            'top_vendors' => OrderItem::query()
                ->selectRaw('vendor_id, SUM(line_total) as revenue')
                ->groupBy('vendor_id')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get(),
            'active_vendors' => Vendor::where('status', Vendor::STATUS_ACTIVE)->count(),
        ]);
    }
}
