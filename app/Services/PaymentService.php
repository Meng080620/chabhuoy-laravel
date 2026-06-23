<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\PaymentFailedException;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Boundary in front of the real payment gateway (Stripe / ABA PayWay / etc.).
 *
 * Swapping providers means editing only this class — the OrderService never
 * talks to a gateway directly. Replace the stub bodies with real SDK calls.
 */
class PaymentService
{
    /**
     * Attempt to capture payment for an order.
     *
     * @return string|null Gateway transaction reference, or null for COD.
     *
     * @throws PaymentFailedException
     */
    public function charge(Order $order, PaymentMethod $method): ?string
    {
        if (! $method->requiresImmediateCapture()) {
            return null; // COD — collected on delivery.
        }

        if ((float) $order->total <= 0) {
            throw new PaymentFailedException('Order total must be greater than zero.');
        }

        // TODO: integrate real gateway. Stubbed as an immediate success so the
        // checkout flow is exercisable end-to-end in tests and local dev.
        return 'txn_'.Str::lower(Str::random(24));
    }
}
