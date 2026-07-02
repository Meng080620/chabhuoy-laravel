<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderCancelledByAdmin;
use App\Events\OrderLineShipped;
use App\Events\OrderPlaced;
use App\Exceptions\InvalidFulfillmentTransitionException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\VendorUnavailableException;
use App\Jobs\SendOrderConfirmation;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly PaymentService $payment,
        private readonly VendorRepositoryInterface $vendors,
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
    public function placeFromCart(User $user, PaymentMethod $method, Address $address): Order
    {
        $order = DB::transaction(function () use ($user, $method, $address): Order {
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
                // Freeze the destination at purchase time; a later edit to the
                // saved address must never rewrite where a placed order shipped.
                ...$address->toOrderSnapshot(),
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
     * When shipping, optional tracking is recorded as a per-vendor Shipment.
     * Passing tracking on a repeat "ship" corrects it without re-shipping the
     * lines — useful for fixing a mistyped number after the fact.
     *
     * @throws InvalidFulfillmentTransitionException
     */
    public function fulfilVendorLines(
        Order $order,
        Vendor $vendor,
        FulfillmentStatus $target,
        ?string $carrier = null,
        ?string $trackingNumber = null,
    ): Order {
        return DB::transaction(function () use ($order, $vendor, $target, $carrier, $trackingNumber): Order {
            $lines = $order->items()
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->get();

            // Earnings credited only for lines that actually transition INTO
            // Delivered on this call — already-delivered lines are skipped, so a
            // repeated "deliver" can never double-pay the vendor.
            $earned = '0';

            foreach ($lines as $line) {
                if ($line->status === $target) {
                    continue;
                }

                if (! $line->status->canTransitionTo($target)) {
                    throw new InvalidFulfillmentTransitionException($line->status, $target);
                }

                $updates = ['status' => $target];

                if ($target === FulfillmentStatus::Delivered) {
                    // Commission is computed and frozen on the line at the moment
                    // it's realised, so a later change to the vendor's rate never
                    // rewrites a past line's take.
                    $commission = bcdiv(bcmul((string) $line->line_total, (string) $vendor->commission_rate, 4), '100', 2);
                    $net = bcsub((string) $line->line_total, $commission, 2);

                    $earned = bcadd($earned, $net, 2);
                    $updates['commission_amount'] = $commission;
                }

                $line->update($updates);
            }

            // Delivery is when the money is realised — credit the vendor inside
            // the same transaction as the status change so the two can't drift.
            // The vendor is credited net of the platform's commission_rate.
            if (bccomp($earned, '0', 2) > 0) {
                $this->vendors->creditPayout($vendor, $earned);
            }

            if ($target === FulfillmentStatus::Shipped && $trackingNumber !== null) {
                $this->recordShipment($order, $vendor, $carrier, $trackingNumber);
            }

            if ($target === FulfillmentStatus::Shipped) {
                // Dispatched on every Shipped call, including a tracking-only
                // correction — the listener's firstOrCreate absorbs repeats safely.
                OrderLineShipped::dispatch($order, $vendor);
            }

            $this->syncOrderStatusFromLines($order);

            return $order->fresh('items');
        });
    }

    /**
     * Upsert the vendor's parcel for this order. Keyed on (order, vendor) so a
     * second call corrects the tracking instead of creating a duplicate; the
     * `shipped_at` stamp is written once, on first ship, and preserved after.
     */
    private function recordShipment(Order $order, Vendor $vendor, ?string $carrier, string $trackingNumber): void
    {
        $shipment = $order->shipments()->firstOrNew(['vendor_id' => $vendor->id]);

        $shipment->fill([
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
        ]);

        if (! $shipment->exists) {
            $shipment->shipped_at = now();
        }

        $shipment->save();
    }

    /**
     * Admin-driven cancellation (fraud, dispute, customer request).
     *
     * The OrderStatus machine only allows Cancelled from Pending or Paid, so a
     * shipped/delivered order is rejected with a 422 before anything mutates.
     * Stock that is still *held* — paid-for lines not yet shipped — is returned
     * to inventory under a row lock so it can't race a concurrent checkout.
     * Delivered lines (possible on a partially-shipped, still-Paid order) have
     * already left inventory, so they're left untouched.
     *
     * A Paid order was captured at checkout, so cancellation reverses it through
     * PaymentService::refund (idempotent, its own ledger row). A Pending order
     * was never captured, so there is nothing to refund. A provider failure
     * throws and rolls the whole cancellation back — we never cancel a paid
     * order without returning the money.
     *
     * @throws InvalidOrderTransitionException
     * @throws \App\Exceptions\PaymentFailedException
     */
    public function cancelAsAdmin(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $from = $order->status;

            if (! $from->canTransitionTo(OrderStatus::Cancelled)) {
                throw new InvalidOrderTransitionException($from, OrderStatus::Cancelled);
            }

            if ($from === OrderStatus::Paid) {
                $this->payment->refund($order);
            }

            $lines = $order->items()->lockForUpdate()->get();

            foreach ($lines as $line) {
                // Delivered goods can't be recalled through a cancellation.
                if ($line->status === FulfillmentStatus::Delivered) {
                    continue;
                }

                // Only Pending lines on a Paid order still hold reserved stock.
                if ($from === OrderStatus::Paid && $line->status === FulfillmentStatus::Pending) {
                    $product = $this->products->findForUpdate($line->product_id);

                    if ($product !== null) {
                        $this->products->incrementStock($product, $line->quantity);
                    }
                }

                $line->update(['status' => FulfillmentStatus::Cancelled]);
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            OrderCancelledByAdmin::dispatch($order);

            return $order->fresh('items');
        });
    }

    /**
     * Rider-driven return: a picked-up parcel that could not be delivered and
     * physically comes back to the vendor (6amMart's picked_up -> returned).
     *
     * Only this vendor's lines are touched. Unlike cancelAsAdmin — which restocks
     * *never-shipped* (Pending) lines — a returned line was Shipped, so its stock
     * left inventory and is now coming back: restore it under a row lock, then
     * move the line to its own terminal Returned state. No payout is credited
     * (delivery never completed), and the vendor's already-earned lines on the
     * same order are left alone.
     */
    public function returnVendorLines(Order $order, Vendor $vendor): Order
    {
        return DB::transaction(function () use ($order, $vendor): Order {
            $lines = $order->items()
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                // Only a Shipped line still holds decremented stock to hand back;
                // Delivered/Cancelled/already-Returned lines are skipped.
                if (! $line->status->canTransitionTo(FulfillmentStatus::Returned)) {
                    continue;
                }

                $product = $this->products->findForUpdate($line->product_id);

                if ($product !== null) {
                    $this->products->incrementStock($product, $line->quantity);
                }

                $line->update(['status' => FulfillmentStatus::Returned]);
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
            ->reject(fn (OrderItem $i) => in_array(
                $i->status,
                [FulfillmentStatus::Cancelled, FulfillmentStatus::Returned],
                true,
            ));

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
