<?php

namespace App\Exceptions;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class VendorUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly ?Product $product = null,
        ?Throwable $previous = null,
    ) {
        $name = $product?->name ?? 'A product';

        parent::__construct(
            "{$name} is no longer available for purchase.",
            previous: $previous,
        );
    }

    /**
     * Render as a clean 422 for API consumers instead of a 500.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'product' => [$this->getMessage()],
            ],
        ], 422);
    }
}
