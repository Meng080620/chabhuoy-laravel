<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDeliveryManStatusRequest;
use App\Http\Resources\DeliveryManResource;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryManController extends Controller
{
    /**
     * List riders for moderation, newest first. Filter by ?status=pending to
     * pull the approval queue.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $deliveryMen = DeliveryMan::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
            )
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return DeliveryManResource::collection($deliveryMen);
    }

    /**
     * Approve, suspend, or reset a rider's status. Suspension takes effect on
     * the rider's next request — EnsureDeliveryManRole rejects a non-active
     * rider, so no token revocation is needed to lock them out.
     */
    public function updateStatus(UpdateDeliveryManStatusRequest $request, DeliveryMan $deliveryMan): DeliveryManResource
    {
        $deliveryMan->update(['status' => $request->validated('status')]);

        return DeliveryManResource::make($deliveryMan);
    }
}
