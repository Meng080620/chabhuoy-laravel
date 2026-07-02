<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryEarningResource;
use App\Models\DeliveryEarning;
use App\Models\DeliveryMan;
use App\Services\DeliveryEarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DeliveryEarningController extends Controller
{
    public function __construct(
        private readonly DeliveryEarningService $earnings,
    ) {}

    /**
     * The rider disbursement ledger, newest first. Optionally narrowed to one
     * rider by ?delivery_man_id= (the rider's public uuid).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $earnings = DeliveryEarning::query()
            ->with('deliveryMan')
            ->when(
                $request->filled('delivery_man_id'),
                fn ($q) => $q->whereHas(
                    'deliveryMan',
                    fn ($d) => $d->where('uuid', $request->string('delivery_man_id')),
                ),
            )
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return DeliveryEarningResource::collection($earnings);
    }

    /**
     * Disburse a rider's outstanding wallet balance and record it in the
     * ledger. Refuses (422) when nothing is owed.
     */
    public function store(DeliveryMan $deliveryMan): JsonResponse
    {
        $earning = $this->earnings->process($deliveryMan);

        if ($earning === null) {
            throw ValidationException::withMessages([
                'delivery_man' => 'This rider has no outstanding wallet balance to pay out.',
            ]);
        }

        return DeliveryEarningResource::make($earning->load('deliveryMan'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
