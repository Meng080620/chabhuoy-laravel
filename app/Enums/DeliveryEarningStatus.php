<?php

namespace App\Enums;

/**
 * Lifecycle of a single rider disbursement.
 *
 * A parallel of {@see PayoutStatus} for the rider wallet rather than the
 * vendor payout_balance — kept as its own enum so the two domains never get
 * conflated even though today's disbursement provider is a stub for both
 * (lands Completed immediately; Pending/Failed are ready for a real provider).
 */
enum DeliveryEarningStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
