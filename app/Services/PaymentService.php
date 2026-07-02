<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentFailedException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Contracts\PaymentGateway;

/**
 * Domain boundary in front of the payment provider. It owns the idempotency
 * rules and the Payment ledger; the actual outbound call lives behind the
 * injected {@see PaymentGateway} port, so swapping providers means rebinding one
 * adapter and changing nothing here or in OrderService.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

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

        // The only line that actually leaves the process. A decline is recorded
        // as a Failed row before rethrowing, so the "prior failed attempt" guard
        // above catches a retry instead of silently re-charging.
        try {
            $reference = $this->gateway->charge((string) $order->total, $key);
        } catch (PaymentFailedException $e) {
            Payment::create([
                'order_id' => $order->id,
                'idempotency_key' => $key,
                'reference' => null,
                'status' => PaymentStatus::Failed,
                'amount' => $order->total,
            ]);

            throw $e;
        }

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
     * Reverse the capture for an order, idempotently. Returns the refund ledger
     * row, or null when there is nothing to reverse (COD / never captured).
     *
     * The refund is its own append-only Payment row (status Refunded) keyed on a
     * distinct `refund_*` idempotency key — so the original capture stays
     * auditable and a repeated refund replays instead of reversing twice.
     *
     * @throws PaymentFailedException
     */
    public function refund(Order $order): ?Payment
    {
        $capture = Payment::where('order_id', $order->id)
            ->where('status', PaymentStatus::Succeeded)
            ->latest('id')
            ->first();

        if ($capture === null) {
            return null; // Nothing was ever captured.
        }

        $refundKey = 'refund_'.$this->idempotencyKeyFor($order);

        $existing = Payment::where('idempotency_key', $refundKey)->first();

        if ($existing !== null) {
            return $existing; // Replay: already refunded.
        }

        $reference = $this->gateway->refund(
            (string) $capture->amount,
            $refundKey,
            (string) $capture->reference,
        );

        return Payment::create([
            'order_id' => $order->id,
            'idempotency_key' => $refundKey,
            'reference' => $reference,
            'status' => PaymentStatus::Refunded,
            'amount' => $capture->amount,
        ]);
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
