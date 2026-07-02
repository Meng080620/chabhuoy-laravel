<?php

namespace App\Services\Gateways;

use App\Services\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The default, credential-free payment adapter: logs the intent and returns a
 * synthetic reference, always succeeding. It lets the whole checkout + refund
 * flow run in tests and local dev. Replace the binding with a real SDK-backed
 * adapter (e.g. StripePaymentGateway) to go live — nothing else changes.
 */
class LogPaymentGateway implements PaymentGateway
{
    public function charge(string $amount, string $idempotencyKey): string
    {
        Log::info('payment.charge', ['amount' => $amount, 'idempotency_key' => $idempotencyKey]);

        return 'txn_'.Str::lower(Str::random(24));
    }

    public function refund(string $amount, string $idempotencyKey, string $originalReference): string
    {
        Log::info('payment.refund', [
            'amount' => $amount,
            'idempotency_key' => $idempotencyKey,
            'original_reference' => $originalReference,
        ]);

        return 'rfnd_'.Str::lower(Str::random(24));
    }
}
