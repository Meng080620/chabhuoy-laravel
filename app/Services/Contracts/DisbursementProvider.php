<?php

namespace App\Services\Contracts;

use App\Exceptions\DisbursementFailedException;

/**
 * The raw external payout rail (bank transfer / wallet top-up) that sends money
 * *out* of the platform to a vendor or rider. The ledger bookkeeping (Payout /
 * DeliveryEarning rows, balance reset, row-lock atomicity) stays in the calling
 * service; this seam is only the outbound call.
 *
 * A thrown DisbursementFailedException is the contract for "the money did not
 * move" — the caller's surrounding transaction rolls back, leaving the balance
 * intact for a retry.
 */
interface DisbursementProvider
{
    /**
     * Send `$amount` out of the platform. `$reference` is our own stable id for
     * this disbursement (passed for idempotency + traceability). Returns the
     * provider's disbursement reference.
     *
     * @throws DisbursementFailedException
     */
    public function send(string $amount, string $reference): string;
}
