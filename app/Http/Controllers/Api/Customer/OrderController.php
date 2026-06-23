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
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $this->orderRepository->paginateForUser($request->user()),
        );
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->authorize('view', $order);

        return OrderResource::make($order->load('items'));
    }

    public function store(StoreOrderRequest $request): OrderResource
    {
        $order = $this->orders->placeFromCart(
            $request->user(),
            $request->paymentMethod(),
        );

        return OrderResource::make($order)
            ->additional(['message' => 'Order placed successfully.']);
    }
}
