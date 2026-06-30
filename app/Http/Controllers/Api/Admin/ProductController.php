<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProductStatusRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Every vendor's catalogue in one moderation list, newest first. Unlike the
     * public storefront, this ignores the active-vendor/visibility rules — an
     * admin must see disabled and suspended-vendor products to moderate them.
     * Filterable by ?status=active|inactive, ?vendor_id=, and ?search=.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('is_active', (string) $request->string('status') === 'active'),
            )
            ->when(
                // By vendor *uuid* — the only vendor id the client ever sees
                // (VendorResource exposes uuid, never the internal bigint).
                $request->filled('vendor_id'),
                fn ($q) => $q->whereHas(
                    'vendor',
                    fn ($v) => $v->where('uuid', $request->string('vendor_id')),
                ),
            )
            ->when(
                $request->filled('search'),
                function ($q) use ($request): void {
                    $term = trim((string) $request->string('search'));

                    // Product name (partial) or its public uuid (exact).
                    // Grouped so the OR can't leak past the status/vendor filters.
                    $q->where(function ($q) use ($term): void {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('uuid', $term);
                    });
                },
            )
            ->with(['vendor', 'category'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    /**
     * Soft-enable or disable a product (moderation toggle). Resolved by uuid.
     */
    public function update(UpdateProductStatusRequest $request, Product $product): ProductResource
    {
        $product->update(['is_active' => $request->validated('is_active')]);

        return ProductResource::make($product->load(['vendor', 'category']));
    }
}
