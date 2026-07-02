<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateCartRequest;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartFor($request)->load('items.product');

        return response()->json([
            'items' => $cart->items->map(fn ($item) => [
                'product_id' => $item->product?->uuid,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->product?->price,
            ]),
        ]);
    }

    /**
     * Upsert a line. Sending quantity sets the absolute quantity (idempotent),
     * which is friendlier for clients than a relative add.
     */
    public function update(UpdateCartRequest $request): JsonResponse
    {
        $cart = $this->cartFor($request);
        $product = Product::where('uuid', $request->validated('product_id'))->firstOrFail();

        $cart->items()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $request->integer('quantity')],
        );

        return response()->json(['message' => 'Cart updated.']);
    }

    public function destroy(Request $request, string $productId): JsonResponse
    {
        $product = Product::where('uuid', $productId)->firstOrFail();

        $this->cartFor($request)->items()->where('product_id', $product->id)->delete();

        return response()->json(['message' => 'Item removed.']);
    }

    private function cartFor(Request $request): Cart
    {
        return $request->user()->cart()->firstOrCreate([]);
    }
}
