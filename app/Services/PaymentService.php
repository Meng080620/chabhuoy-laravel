<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentFailedException;
use App\Models\Order;
use App\Models\Payment;
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
     * Attempt to capture payment for an order, idempotently.
     *
     * A deterministic idempotency key (derived from the order) means a retried
     * charge — network timeout, transaction replay, client double-submit of the
     * same order — never captures twice: the prior result is replayed instead.
     * The unique index on payments.idempotency_key is the structural guarantee;
     * the lookup below is the fast path.
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

        $key = $this->idempotencyKeyFor($order);

        $existing = Payment::where('idempotency_key', $key)->first();

        if ($existing !== null) {
            // Replay: hand back the original outcome without re-hitting the gateway.
            if ($existing->status === PaymentStatus::Succeeded) {
                return $existing->reference;
            }

            throw new PaymentFailedException('A previous payment for this order failed.');
        }

        // TODO: integrate real gateway here, passing $key as the provider's
        // Idempotency-Key header. Stubbed as an immediate success so the
        // checkout flow is exercisable end-to-end in tests and local dev.
        $reference = 'txn_'.Str::lower(Str::random(24));

        Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => $key,
            'reference' => $reference,
            'status' => PaymentStatus::Succeeded,
            'amount' => $order->total,
        ]);

        return $reference;
    }

    /**
     * Stable across retries of the same order, unique across different orders —
     * exactly the property an idempotency key needs.
     */
    private function idempotencyKeyFor(Order $order): string
    {
        return 'order_'.$order->uuid;
    }
}
