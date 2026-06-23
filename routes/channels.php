<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Private channel so a customer can subscribe to live status updates for
| their own order only.
*/
Broadcast::channel('orders.{orderUuid}', function ($user, string $orderUuid) {
    $order = Order::where('uuid', $orderUuid)->first();

    return $order !== null && $order->user_id === $user->id;
});
