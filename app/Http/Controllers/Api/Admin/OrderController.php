<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * Every order on the platform, newest first. Unlike the vendor view, an
     * admin sees all lines and the customer behind each order. Filterable by
     * ?status= and ?vendor_id= for moderation and dispute work.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
            )
            ->when(
                $request->filled('vendor_id'),
                fn ($q) => $q->whereHas(
                    'items',
                    fn ($i) => $i->where('vendor_id', $request->integer('vendor_id')),
                ),
            )
            ->when(
                $request->filled('search'),
                function ($q) use ($request): void {
                    $term = trim((string) $request->string('search'));

                    // Order # (uuid) or the customer behind it (name/email).
                    // Grouped so the OR can't leak past the status/vendor filters.
                    $q->where(function ($q) use ($term): void {
                        $q->where('uuid', $term)
                            ->orWhereHas('user', fn ($u) => $u
                                ->where('name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%"));
                    });
                },
            )
            ->with(['items', 'user'])
            ->latest('placed_at')
            ->paginate((int) $request->integer('per_page', 20));

        return OrderResource::collection($orders);
    }

    /**
     * Cancel an order (e.g. fraud, dispute, customer request). Held stock is
     * returned to inventory; the OrderStatus state machine blocks cancelling an
     * order that has already shipped or delivered (→ 422).
     */
    public function update(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->cancelAsAdmin($order);

        return OrderResource::make($order->load(['items', 'user']));
    }
}
