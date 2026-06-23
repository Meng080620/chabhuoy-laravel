<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['category_id', 'vendor_id', 'search']);

        $products = $this->products->paginate(
            array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            perPage: (int) $request->integer('per_page', 20),
        );

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make($product->load(['category', 'vendor']));
    }
}
