<?php

namespace App\Listeners;

use App\Enums\DeliveryAssignmentStatus;
use App\Events\OrderCancelledByAdmin;
use App\Models\DeliveryAssignment;

class CancelDeliveryAssignmentsForOrder
{
    public function handle(OrderCancelledByAdmin $event): void
    {
        DeliveryAssignment::where('order_id', $event->order->id)
            ->whereNotIn('status', [
                DeliveryAssignmentStatus::Delivered->value,
                DeliveryAssignmentStatus::Cancelled->value,
                DeliveryAssignmentStatus::Returned->value,
            ])
            ->update(['status' => DeliveryAssignmentStatus::Cancelled]);
    }
}
