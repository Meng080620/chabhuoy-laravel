<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Events\ProductStockLow;
use App\Repositories\Contracts\ProductRepositoryInterface;

/**
 * Runs synchronously (NOT queued) so the stock decrement happens inside the
 * same transaction as the order. Queuing it would let an order commit before
 * stock moved, reopening the oversell window.
 */
class DecrementProductStock
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function handle(OrderPlaced $event): void
    {
        $event->order->loadMissing('items.product');

        foreach ($event->order->items as $item) {
            $product = $item->product;

            if ($product === null) {
                continue;
            }

            $this->products->decrementStock($product, $item->quantity);

            if ($product->refresh()->isLowOnStock()) {
                ProductStockLow::dispatch($product, $product->stock);
            }
        }
    }
}
