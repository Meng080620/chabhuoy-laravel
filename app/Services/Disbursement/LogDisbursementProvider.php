<?php

namespace App\Services\Disbursement;

use App\Services\Contracts\DisbursementProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default credential-free payout adapter: logs the intent and returns a
 * synthetic reference, always succeeding. Swap the binding for a real
 * bank/wallet SDK adapter to go live — the payout/earning services don't change.
 */
class LogDisbursementProvider implements DisbursementProvider
{
    public function send(string $amount, string $reference): string
    {
        Log::info('disbursement.send', ['amount' => $amount, 'reference' => $reference]);

        return 'disb_'.Str::lower(Str::random(24));
    }
}
