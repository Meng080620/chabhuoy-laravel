<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateOrderFulfillmentRequest;
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
     * Orders that contain at least one of this vendor's items.
     * Eager-loaded items are constrained to the vendor so a vendor never sees
     * another vendor's lines on a shared order.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $vendorId = $request->user()->vendor->id;

        $orders = Order::query()
            ->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['items' => fn ($q) => $q->where('vendor_id', $vendorId)])
            ->latest('placed_at')
            ->paginate((int) $request->integer('per_page', 20));

        return OrderResource::collection($orders);
    }

    /**
     * Advance this vendor's lines on the order (e.g. mark them shipped).
     * 404 — not 403 — when the vendor has no line on the order, so the
     * existence of other customers' orders isn't leaked.
     */
    public function update(UpdateOrderFulfillmentRequest $request, Order $order): OrderResource
    {
        $vendor = $request->user()->vendor;

        abort_unless(
            $order->items()->where('vendor_id', $vendor->id)->exists(),
            404,
        );

        $order = $this->orders->fulfilVendorLines(
            $order,
            $vendor,
            $request->fulfillmentStatus(),
            $request->carrier(),
            $request->trackingNumber(),
        );

        // Return only this vendor's lines and their own parcel, consistent with index().
        $order->setRelation(
            'items',
            $order->items->where('vendor_id', $vendor->id)->values(),
        );
        $order->load(['shipments' => fn ($q) => $q->where('vendor_id', $vendor->id)]);

        return OrderResource::make($order);
    }
}
