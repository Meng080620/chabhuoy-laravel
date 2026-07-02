<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderLineShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Vendor $vendor,
    ) {}
}
