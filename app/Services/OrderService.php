<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Exceptions\OutOfStockException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly PaymentService $payment,
    ) {
    }

    /**
     * Turn the user's cart into a paid order.
     *
     * The whole thing runs in one transaction with row-level locks on each
     * product so two simultaneous checkouts can't oversell the same stock.
     * Stock is only decremented after payment succeeds (via the OrderPlaced
     * listener), and the confirmation job is queued for *after* commit.
     *
     * @throws OutOfStockException
     * @throws \App\Exceptions\PaymentFailedException
     */
    public function placeFromCart(User $user, PaymentMethod $method): Order
    {
        $order = DB::transaction(function () use ($user, $method): Order {
            $cart = $user->cart()->with('items.product')->first();

            if ($cart === null || $cart->items->isEmpty()) {
                throw new RuntimeException('Cannot place an order with an empty cart.');
            }

            [$lineItems, $total] = $this->buildLineItems($cart);

            $order = $this->orders->create([
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'payment_method' => $method,
                'total' => $total,
                'placed_at' => now(),
            ], $lineItems);

            // Throws PaymentFailedException -> transaction rolls back, no stock moved.
            $this->payment->charge($order, $method);

            $order->update(['status' => OrderStatus::Paid]);

            // Synchronous listener decrements stock inside this transaction.
            OrderPlaced::dispatch($order);

            $cart->items()->delete();

            return $order;
        });

        // Side effects that must NOT roll back the order go after commit.
        SendOrderConfirmation::dispatch($order)->afterCommit();

        return $order;
    }

    /**
     * Validate availability under lock and compute the order lines + total.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function buildLineItems(Cart $cart): array
    {
        $lineItems = [];
        $total = '0';

        foreach ($cart->items as $item) {
            $product = $this->products->findForUpdate($item->product_id);

            if ($product === null || ! $product->hasStockFor($item->quantity)) {
                throw new OutOfStockException(
                    product: $product,
                    requested: $item->quantity,
                    available: $product?->stock ?? 0,
                );
            }

            $lineTotal = bcmul((string) $product->price, (string) $item->quantity, 2);
            $total = bcadd($total, $lineTotal, 2);

            $lineItems[] = [
                'product_id' => $product->id,
                'vendor_id' => $product->vendor_id,
                'product_name' => $product->name,
                'quantity' => $item->quantity,
                'unit_price' => $product->price,
                'line_total' => $lineTotal,
            ];
        }

        return [$lineItems, $total];
    }
}
