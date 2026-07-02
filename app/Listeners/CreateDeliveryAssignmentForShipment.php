<?php

namespace App\Listeners;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderLineShipped;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;

/**
 * Turns a vendor's "shipped" transition into a rider-assignable job.
 *
 * Grained on (order, vendor) — the same grain as Shipment — rather than on
 * shipment_id, because a vendor can ship without ever typing a tracking
 * number (no Shipment row created), and that parcel must still be
 * assignable to a rider. `firstOrCreate` makes this idempotent: a
 * tracking-only correction re-dispatches the same event but never
 * duplicates the assignment.
 */
class CreateDeliveryAssignmentForShipment
{
    public function handle(OrderLineShipped $event): void
    {
        DeliveryAssignment::firstOrCreate(
            ['order_id' => $event->order->id, 'vendor_id' => $event->vendor->id],
            [
                'shipment_id' => Shipment::query()
                    ->where('order_id', $event->order->id)
                    ->where('vendor_id', $event->vendor->id)
                    ->value('id'),
                'status' => DeliveryAssignmentStatus::Available,
                'delivery_fee' => config('delivery.flat_fee'),
                'cod_amount' => $event->order->payment_method === PaymentMethod::Cod
                    ? $event->vendor->orderItems()
                        ->where('order_id', $event->order->id)
                        ->where('status', FulfillmentStatus::Shipped)
                        ->sum('line_total')
                    : 0,
            ],
        );
    }
}
