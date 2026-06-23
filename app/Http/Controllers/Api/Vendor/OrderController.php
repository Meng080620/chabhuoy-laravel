<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
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
}
