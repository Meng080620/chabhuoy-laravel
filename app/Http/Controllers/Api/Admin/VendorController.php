<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVendorCommissionRequest;
use App\Http\Requests\Admin\UpdateVendorStatusRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorController extends Controller
{
    /**
     * List vendors for moderation, newest first. Filter by ?status=pending to
     * pull the approval queue.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $vendors = Vendor::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
            )
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return VendorResource::collection($vendors);
    }

    /**
     * Approve, suspend, or reset a vendor's status. Suspension takes effect on
     * the vendor's next request — EnsureVendorRole rejects a non-active vendor,
     * so no token revocation is needed to lock them out.
     */
    public function updateStatus(UpdateVendorStatusRequest $request, Vendor $vendor): VendorResource
    {
        $vendor->update(['status' => $request->validated('status')]);

        return VendorResource::make($vendor);
    }

    /**
     * Set the platform's take rate for this vendor. Only affects lines
     * delivered after this call — a line's commission is frozen at the
     * moment it's credited (see OrderService::fulfilVendorLines).
     */
    public function updateCommission(UpdateVendorCommissionRequest $request, Vendor $vendor): VendorResource
    {
        $vendor->update(['commission_rate' => $request->validated('commission_rate')]);

        return VendorResource::make($vendor);
    }
}
