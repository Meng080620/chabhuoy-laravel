<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Exceptions\InvalidFulfillmentTransitionException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\VendorUnavailableException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
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
    ) {}

    /**
     * Turn the user's cart into a paid order.
     *
     * The whole thing runs in one transaction with row-level locks on each
     * product so two simultaneous checkouts can't oversell the same stock.
     * Stock is only decremented after payment succeeds (via the OrderPlaced
     * listener), and the confirmation job is queued for *after* commit.
     *
     * @throws OutOfStockException
     * @throws PaymentFailedException
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
     * Advance a single vendor's lines on an order to the target fulfillment
     * status, then recompute the order-level rollup.
     *
     * Only the vendor's own lines are touched, so on a shared (multi-vendor)
     * order one vendor shipping never marks another vendor's items shipped.
     * Lines are locked for the duration so two concurrent updates can't race
     * the rollup. Idempotent: a line already at the target is skipped.
     *
     * @throws InvalidFulfillmentTransitionException
     */
    public function fulfilVendorLines(Order $order, Vendor $vendor, FulfillmentStatus $target): Order
    {
        return DB::transaction(function () use ($order, $vendor, $target): Order {
            $lines = $order->items()
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                if ($line->status === $target) {
                    continue;
                }

                if (! $line->status->canTransitionTo($target)) {
                    throw new InvalidFulfillmentTransitionException($line->status, $target);
                }

                $line->update(['status' => $target]);
            }

            $this->syncOrderStatusFromLines($order);

            return $order->fresh('items');
        });
    }

    /**
     * Derive the order's coarse status from its line statuses. The order is
     * Shipped only once every (non-cancelled) line has shipped, Delivered only
     * once every line is delivered — a partially-shipped order stays as-is.
     */
    private function syncOrderStatusFromLines(Order $order): void
    {
        $lines = $order->items()->get(['status'])
            ->reject(fn (OrderItem $i) => $i->status === FulfillmentStatus::Cancelled);

        if ($lines->isEmpty()) {
            return;
        }

        $rollup = match (true) {
            $lines->every(fn (OrderItem $i) => $i->status === FulfillmentStatus::Delivered) => OrderStatus::Delivered,
            $lines->every(fn (OrderItem $i) => in_array(
                $i->status,
                [FulfillmentStatus::Shipped, FulfillmentStatus::Delivered],
                true,
            )) => OrderStatus::Shipped,
            default => null,
        };

        if ($rollup !== null
            && $order->status !== $rollup
            && $order->status->canTransitionTo($rollup)
        ) {
            $order->update(['status' => $rollup]);
        }
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

            // A product can sit in a cart from before its vendor was suspended
            // (or its listing deactivated). The storefront hides it, but the
            // cart is a separate snapshot — so re-check sellability here, under
            // the same lock, before it becomes an order line nobody can fulfil.
            if (! $product->isPubliclyVisible()) {
                throw new VendorUnavailableException($product);
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
