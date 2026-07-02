<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Vendor;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Attach (or replace) a product's image. On replace, the previous file is
     * deleted first so orphaned uploads don't accumulate on disk.
     */
    public function setImage(Product $product, UploadedFile $image): Product
    {
        $this->deleteImageFile($product);

        return $this->products->update($product, [
            'image_path' => $image->store('products', 'public'),
        ]);
    }

    /**
     * Detach a product's image: remove the file and null the column. Safe to call
     * when there is no image (no-op on the file, still nulls the already-null column).
     */
    public function removeImage(Product $product): Product
    {
        $this->deleteImageFile($product);

        return $this->products->update($product, ['image_path' => null]);
    }

    private function deleteImageFile(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
    }

    private function uniqueSlug(string $name): string
    {
        return Str::slug($name).'-'.Str::lower(Str::random(6));
    }
}
