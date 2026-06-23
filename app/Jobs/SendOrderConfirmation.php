<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Order $order)
    {
    }

    public function handle(): void
    {
        // TODO: send a real notification (Mail / Telegram / SMS).
        Log::info('Order confirmation dispatched', [
            'order_uuid' => $this->order->uuid,
            'user_id' => $this->order->user_id,
        ]);
    }
}
