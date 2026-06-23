<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $this->products->forVendor($request->user()->vendor->id),
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->createForVendor(
            $request->user()->vendor,
            $request->validated(),
        );

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }

    public function update(StoreProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        return ProductResource::make(
            $this->service->update($product, $request->validated()),
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
