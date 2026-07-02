<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The payout rail rejected the transfer. Thrown by a DisbursementProvider so the
 * calling service's transaction rolls back — the ledger row and balance reset
 * are both undone, leaving the balance owed for a retry.
 */
class DisbursementFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'The disbursement could not be processed.',
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
