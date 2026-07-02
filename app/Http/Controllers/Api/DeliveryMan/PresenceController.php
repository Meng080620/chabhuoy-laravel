<?php

namespace App\Http\Controllers\Api\DeliveryMan;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryMan\RecordLocationRequest;
use App\Http\Requests\DeliveryMan\UpdateActiveStatusRequest;
use App\Http\Requests\DeliveryMan\UpdateFcmTokenRequest;
use App\Http\Resources\DeliveryManResource;

/**
 * Simple single-column presence writes — no atomicity concern, so these go
 * straight through the model (mirrors Customer\AddressController::setDefault),
 * unlike the wallet/cash-in-hand fields which need DeliveryManRepositoryInterface's
 * atomic increment/decrement.
 */
class PresenceController extends Controller
{
    public function updateActiveStatus(UpdateActiveStatusRequest $request): DeliveryManResource
    {
        $rider = $request->user()->deliveryMan;
        $rider->update(['is_online' => $request->boolean('is_online')]);

        return DeliveryManResource::make($rider);
    }

    public function recordLocation(RecordLocationRequest $request): DeliveryManResource
    {
        $rider = $request->user()->deliveryMan;
        $rider->update([
            'last_lat' => $request->validated('lat'),
            'last_lng' => $request->validated('lng'),
        ]);

        return DeliveryManResource::make($rider);
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): DeliveryManResource
    {
        $rider = $request->user()->deliveryMan;
        $rider->update(['fcm_token' => $request->validated('fcm_token')]);

        return DeliveryManResource::make($rider);
    }
}
