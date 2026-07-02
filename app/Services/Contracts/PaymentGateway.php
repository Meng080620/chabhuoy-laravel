<?php

namespace App\Services\Contracts;

use App\Exceptions\PaymentFailedException;

/**
 * The raw external payment provider (Stripe / ABA PayWay / …). This is the only
 * seam that actually leaves the process; everything above it — idempotency, the
 * Payment ledger, refund bookkeeping — is domain logic in PaymentService.
 *
 * Swapping providers means adding one adapter and rebinding it; no caller
 * changes. The bound default (LogPaymentGateway) makes checkout exercisable
 * end-to-end with no real credentials.
 */
interface PaymentGateway
{
    /**
     * Capture funds. `$idempotencyKey` is passed to the provider so a retried
     * charge never captures twice. Returns the provider's transaction reference.
     *
     * @throws PaymentFailedException on decline or gateway error.
     */
    public function charge(string $amount, string $idempotencyKey): string;

    /**
     * Refund a previously-captured charge. `$originalReference` identifies the
     * transaction to reverse. Returns the provider's refund reference.
     *
     * @throws PaymentFailedException
     */
    public function refund(string $amount, string $idempotencyKey, string $originalReference): string;
}
