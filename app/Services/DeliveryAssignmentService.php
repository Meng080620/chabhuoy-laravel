<?php

namespace App\Services;

use App\Enums\DeliveryAssignmentStatus;
use App\Enums\FulfillmentStatus;
use App\Exceptions\DeliveryManCashCeilingExceededException;
use App\Exceptions\DeliveryManOfflineException;
use App\Exceptions\DeliveryManOutsideServiceZoneException;
use App\Exceptions\DeliveryManOverCapacityException;
use App\Exceptions\InvalidDeliveryAssignmentTransitionException;
use App\Exceptions\InvalidDeliveryOtpException;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryMan;
use App\Repositories\Contracts\DeliveryManRepositoryInterface;
use App\Support\GeoDistance;
use Illuminate\Support\Facades\DB;

class DeliveryAssignmentService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly DeliveryManRepositoryInterface $deliveryMen,
    ) {}

    /**
     * A rider claims an unassigned parcel.
     *
     * Guards (in order): the row must still be Available (re-checked under
     * lock, so two riders racing the same job can't both win); the rider
     * must be online; within the service zone (when the geofence is enabled);
     * under the concurrent-job limit; and accepting this job's COD amount must
     * not push cash_in_hand over the ceiling. The sent GPS is both the geofence
     * input and the recorded proof-of-proximity on the rider.
     *
     * @throws InvalidDeliveryAssignmentTransitionException
     * @throws DeliveryManOfflineException
     * @throws DeliveryManOutsideServiceZoneException
     * @throws DeliveryManOverCapacityException
     * @throws DeliveryManCashCeilingExceededException
     */
    public function accept(DeliveryAssignment $assignment, DeliveryMan $rider, string $lat, string $lng): DeliveryAssignment
    {
        return DB::transaction(function () use ($assignment, $rider, $lat, $lng): DeliveryAssignment {
            $locked = DeliveryAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(DeliveryAssignmentStatus::Accepted)) {
                throw new InvalidDeliveryAssignmentTransitionException($locked->status, DeliveryAssignmentStatus::Accepted);
            }

            if (! $rider->is_online) {
                throw new DeliveryManOfflineException;
            }

            if (config('delivery.service_zone.enabled')) {
                $distanceKm = GeoDistance::haversineKm(
                    (float) config('delivery.service_zone.center_lat'),
                    (float) config('delivery.service_zone.center_lng'),
                    (float) $lat,
                    (float) $lng,
                );

                if ($distanceKm > (float) config('delivery.service_zone.radius_km')) {
                    throw new DeliveryManOutsideServiceZoneException;
                }
            }

            $concurrent = DeliveryAssignment::where('delivery_man_id', $rider->id)
                ->whereIn('status', [DeliveryAssignmentStatus::Accepted, DeliveryAssignmentStatus::PickedUp])
                ->count();
            $limit = (int) config('delivery.max_concurrent_orders');

            if ($concurrent >= $limit) {
                throw new DeliveryManOverCapacityException($limit);
            }

            $ceiling = (string) config('delivery.max_cash_in_hand');
            $projected = bcadd((string) $rider->cash_in_hand, (string) $locked->cod_amount, 2);

            if (bccomp($projected, $ceiling, 2) > 0) {
                throw new DeliveryManCashCeilingExceededException($ceiling);
            }

            $locked->update([
                'delivery_man_id' => $rider->id,
                'status' => DeliveryAssignmentStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $rider->update(['last_lat' => $lat, 'last_lng' => $lng]);

            return $locked->fresh();
        });
    }

    /**
     * Advance an assignment already owned by this rider. Delivering credits
     * the rider's wallet by the snapshotted flat fee (and cash_in_hand by the
     * COD amount, if any), then reuses OrderService::fulfilVendorLines — the
     * same call the vendor flow already makes — so the vendor's own payout
     * credit fires exactly once, unaffected by this rider-side change.
     *
     * @throws InvalidDeliveryAssignmentTransitionException
     * @throws InvalidDeliveryOtpException
     */
    public function advance(DeliveryAssignment $assignment, DeliveryMan $rider, DeliveryAssignmentStatus $target, ?string $otp = null, ?string $proofPhotoPath = null): DeliveryAssignment
    {
        return DB::transaction(function () use ($assignment, $rider, $target, $otp, $proofPhotoPath): DeliveryAssignment {
            $locked = DeliveryAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw new InvalidDeliveryAssignmentTransitionException($locked->status, $target);
            }

            if ($target === DeliveryAssignmentStatus::PickedUp) {
                $locked->update([
                    'status' => $target,
                    'picked_up_at' => now(),
                    'otp' => config('delivery.otp_required') ? (string) random_int(100000, 999999) : null,
                ]);

                return $locked->fresh();
            }

            if ($target === DeliveryAssignmentStatus::Delivered) {
                if (config('delivery.otp_required') && $locked->otp !== $otp) {
                    throw new InvalidDeliveryOtpException;
                }

                $locked->order->loadMissing('items');

                $this->orders->fulfilVendorLines($locked->order, $locked->vendor, FulfillmentStatus::Delivered);

                $this->deliveryMen->creditWallet($rider, (string) $locked->delivery_fee);

                if (bccomp((string) $locked->cod_amount, '0', 2) > 0) {
                    $this->deliveryMen->incrementCashInHand($rider, (string) $locked->cod_amount);
                }

                $locked->update([
                    'status' => $target,
                    'delivered_at' => now(),
                    'proof_photo_path' => $proofPhotoPath,
                ]);

                return $locked->fresh();
            }

            // Returned — the delivery failed and the parcel goes back to the
            // vendor. Restore the vendor's line stock and move those lines to the
            // terminal Returned state; no wallet/cash credit, since nothing was
            // delivered.
            $locked->order->loadMissing('items');

            $this->orders->returnVendorLines($locked->order, $locked->vendor);

            $locked->update(['status' => $target]);

            return $locked->fresh();
        });
    }
}
