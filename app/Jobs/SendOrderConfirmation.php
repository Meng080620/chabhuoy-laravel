<?php

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Order $order) {}

    public function handle(): void
    {
        $this->order->loadMissing('user');

        $this->order->user?->notify(new OrderConfirmation($this->order));
    }
}
