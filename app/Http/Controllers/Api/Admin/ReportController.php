<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** Order states whose revenue is actually captured (money received, not reversed). */
    private const CAPTURED_STATUSES = [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered];

    /**
     * Admin dashboard KPIs in a single call. Every figure is a SQL aggregate —
     * no models are hydrated — so the payload cost is a handful of index scans
     * regardless of table size. Money is emitted as fixed 2-decimal strings to
     * match the decimal:2 contract the web terminal parses with Zod.
     *
     * Scale note: the order histogram and low-stock scan lean on indexes on
     * orders.status / orders.placed_at and products.(stock, low_stock_threshold).
     * If those are missing they degrade to full scans as the tables grow — add
     * them before this endpoint is hit on every admin page load.
     */
    public function dashboard(Request $request): JsonResponse
    {
        // One grouped pass yields both the status histogram and captured revenue,
        // instead of a count-per-status fan-out.
        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as cnt, SUM(total) as revenue')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Order $row) => $row->status->value);

        $counts = collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $s) => [$s->value => (int) ($byStatus[$s->value]->cnt ?? 0)]);

        $capturedRevenue = collect(self::CAPTURED_STATUSES)
            ->sum(fn (OrderStatus $s) => (float) ($byStatus[$s->value]->revenue ?? 0));

        $owed = Vendor::where('payout_balance', '>', 0);
        $customers = User::where('role', UserRole::Customer);

        return response()->json([
            'revenue' => [
                'captured' => $this->money($capturedRevenue),
                'today' => $this->money(
                    Order::whereIn('status', self::CAPTURED_STATUSES)
                        ->whereDate('placed_at', today())
                        ->sum('total')
                ),
            ],
            'orders' => [
                'total' => $counts->sum(),
                'by_status' => $counts,
            ],
            'customers' => [
                'total' => (clone $customers)->count(),
                'new_this_week' => (clone $customers)
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count(),
            ],
            'payouts' => [
                'pending_amount' => $this->money((clone $owed)->sum('payout_balance')),
                'pending_count' => (clone $owed)->count(),
            ],
            'catalog' => [
                'low_stock_count' => Product::whereColumn('stock', '<=', 'low_stock_threshold')->count(),
                // `id` is the product **uuid** (the key the rest of the admin/public
                // surface uses), and `name` ships inline — so the dashboard can both
                // label and link each row without a follow-up join. This deliberately
                // avoids the bigint-vs-uuid drift that stranded reports/sales.top_vendors.
                'top_products' => OrderItem::query()
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->selectRaw('products.uuid, products.name, SUM(order_items.line_total) as revenue, SUM(order_items.quantity) as units')
                    ->groupBy('products.uuid', 'products.name')
                    ->orderByDesc('revenue')
                    ->limit(5)
                    ->get()
                    ->map(fn ($row) => [
                        'id' => $row->uuid,
                        'name' => $row->name,
                        'revenue' => $this->money($row->revenue),
                        'units' => (int) $row->units,
                    ]),
            ],
        ]);
    }

    /** Normalise any numeric aggregate to a fixed 2-decimal string (the money contract). */
    private function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
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
