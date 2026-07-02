<?php

namespace App\Http\Controllers\Api\DeliveryMan;

use App\Enums\DeliveryAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryMan\AcceptOrderRequest;
use App\Http\Requests\DeliveryMan\UpdateOrderStatusRequest;
use App\Http\Resources\DeliveryAssignmentResource;
use App\Models\DeliveryAssignment;
use App\Services\DeliveryAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly DeliveryAssignmentService $assignments,
    ) {}

    /**
     * The unassigned pool — any online rider may accept one of these.
     */
    public function latestOrders(Request $request): AnonymousResourceCollection
    {
        $assignments = DeliveryAssignment::query()
            ->where('status', DeliveryAssignmentStatus::Available)
            ->whereNull('delivery_man_id')
            ->with(['order', 'vendor'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return DeliveryAssignmentResource::collection($assignments);
    }

    /**
     * This rider's non-terminal jobs — what they're actively carrying.
     */
    public function currentOrders(Request $request): AnonymousResourceCollection
    {
        $rider = $request->user()->deliveryMan;

        $assignments = DeliveryAssignment::query()
            ->where('delivery_man_id', $rider->id)
            ->whereNotIn('status', [
                DeliveryAssignmentStatus::Delivered,
                DeliveryAssignmentStatus::Cancelled,
                DeliveryAssignmentStatus::Returned,
            ])
            ->with(['order', 'vendor'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return DeliveryAssignmentResource::collection($assignments);
    }

    /**
     * Full history for this rider, including terminal states.
     */
    public function allOrders(Request $request): AnonymousResourceCollection
    {
        $rider = $request->user()->deliveryMan;

        $assignments = DeliveryAssignment::query()
            ->where('delivery_man_id', $rider->id)
            ->with(['order', 'vendor'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return DeliveryAssignmentResource::collection($assignments);
    }

    public function accept(AcceptOrderRequest $request, DeliveryAssignment $deliveryAssignment): DeliveryAssignmentResource
    {
        $rider = $request->user()->deliveryMan;

        $assignment = $this->assignments->accept($deliveryAssignment, $rider, $request->lat(), $request->lng());

        return DeliveryAssignmentResource::make($assignment->load(['order', 'vendor']));
    }

    /**
     * 404 — not 403 — when the assignment belongs to another rider, so the
     * existence of other riders' jobs isn't leaked (mirrors Vendor\OrderController).
     */
    public function updateStatus(UpdateOrderStatusRequest $request, DeliveryAssignment $deliveryAssignment): DeliveryAssignmentResource
    {
        $rider = $request->user()->deliveryMan;

        abort_unless($deliveryAssignment->delivery_man_id === $rider->id, 404);

        // Store the proof photo (validated + required-when-configured in the
        // FormRequest) on the public disk, same as banner images. The service
        // gets a path string, never the UploadedFile — keeping it HTTP-free.
        $proofPhotoPath = $request->hasFile('proof_photo')
            ? $request->file('proof_photo')->store('delivery-proofs', 'public')
            : null;

        $assignment = $this->assignments->advance(
            $deliveryAssignment,
            $rider,
            $request->status(),
            $request->otp(),
            $proofPhotoPath,
        );

        return DeliveryAssignmentResource::make($assignment->load(['order', 'vendor']));
    }
}
