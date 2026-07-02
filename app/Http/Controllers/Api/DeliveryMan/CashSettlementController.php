<?php

namespace App\Http\Controllers\Api\DeliveryMan;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryMan\MakeCollectedCashPaymentRequest;
use App\Http\Resources\DeliveryCashSettlementResource;
use App\Services\DeliveryCashSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CashSettlementController extends Controller
{
    public function __construct(
        private readonly DeliveryCashSettlementService $settlements,
    ) {}

    /**
     * The rider hands collected COD cash back to the platform.
     */
    public function store(MakeCollectedCashPaymentRequest $request): JsonResponse
    {
        $rider = $request->user()->deliveryMan;

        $settlement = $this->settlements->record($rider, $request->amount());

        return DeliveryCashSettlementResource::make($settlement)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
