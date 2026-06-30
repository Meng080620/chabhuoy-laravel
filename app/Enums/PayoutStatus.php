<?php

namespace App\Enums;

/**
 * Lifecycle of a single vendor disbursement.
 *
 * The disbursement provider is a stub today (logs + zeroes the balance), so a
 * processed payout lands as Completed immediately. Pending/Failed exist for the
 * async-provider future: create Pending, then settle to Completed or Failed on
 * the provider callback — without that callback the row stays auditable as-is.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
