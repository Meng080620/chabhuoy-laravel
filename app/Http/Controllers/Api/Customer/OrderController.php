<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orderRepository->paginateForUser($request->user()),
        );
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return OrderResource::make($order->load(['items', 'shipments.vendor']));
    }

    public function store(StoreOrderRequest $request): OrderResource
    {
        // Scoped to the user, so a valid-but-someone-else's address_id can't
        // resolve here even though the request rule already enforces ownership.
        $address = $request->user()->addresses()
            ->where('uuid', $request->validated('address_id'))
            ->firstOrFail();

        $order = $this->orders->placeFromCart(
            $request->user(),
            $request->paymentMethod(),
            $address,
        );

        return OrderResource::make($order)
            ->additional(['message' => 'Order placed successfully.']);
    }
}
