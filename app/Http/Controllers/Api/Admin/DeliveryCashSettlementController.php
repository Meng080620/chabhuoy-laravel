<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryCashSettlementResource;
use App\Models\DeliveryCashSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryCashSettlementController extends Controller
{
    /**
     * Read-only ledger of COD cash riders have handed back, newest first.
     * Optionally narrowed to one rider by ?delivery_man_id=. The settlement
     * itself is rider-initiated (DeliveryMan\CashSettlementController::store).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $settlements = DeliveryCashSettlement::query()
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

        return DeliveryCashSettlementResource::collection($settlements);
    }
}
