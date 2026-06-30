<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Models\Vendor;
use App\Services\VendorPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PayoutController extends Controller
{
    public function __construct(
        private readonly VendorPayoutService $payouts,
    ) {}

    /**
     * The disbursement ledger, newest first. Optionally narrowed to one vendor
     * by ?vendor_id= (the vendor's public uuid, never the internal bigint).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $payouts = Payout::query()
            ->with('vendor')
            ->when(
                $request->filled('vendor_id'),
                fn ($q) => $q->whereHas(
                    'vendor',
                    fn ($v) => $v->where('uuid', $request->string('vendor_id')),
                ),
            )
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return PayoutResource::collection($payouts);
    }

    /**
     * Disburse a vendor's outstanding balance and record it in the ledger.
     * Refuses (422) when nothing is owed — rather than writing a 0.00 payout —
     * so the ledger only ever holds real money movements.
     */
    public function store(Vendor $vendor): JsonResponse
    {
        $payout = $this->payouts->process($vendor);

        if ($payout === null) {
            throw ValidationException::withMessages([
                'vendor' => 'This vendor has no outstanding balance to pay out.',
            ]);
        }

        return PayoutResource::make($payout->load('vendor'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
