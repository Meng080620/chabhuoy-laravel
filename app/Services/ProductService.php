<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vendor;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * Create a product owned by the given vendor.
     *
     * @param array<string, mixed> $data
     */
    public function createForVendor(Vendor $vendor, array $data): Product
    {
        $data['vendor_id'] = $vendor->id;
        $data['slug'] = $this->uniqueSlug($data['name']);

        return $this->products->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name']);
        }

        return $this->products->update($product, $data);
    }

    private function uniqueSlug(string $name): string
    {
        return Str::slug($name).'-'.Str::lower(Str::random(6));
    }
}
