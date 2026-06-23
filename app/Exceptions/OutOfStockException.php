<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;
use Throwable;

class OutOfStockException extends RuntimeException
{
    public function __construct(
        public readonly ?Product $product = null,
        public readonly int $requested = 0,
        public readonly int $available = 0,
        ?Throwable $previous = null,
    ) {
        $name = $product?->name ?? 'A product';

        parent::__construct(
            "{$name} is out of stock (requested {$requested}, available {$available}).",
            previous: $previous,
        );
    }

    /**
     * Render as a clean 422 for API consumers instead of a 500.
     */
    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'product' => [$this->getMessage()],
            ],
        ], 422);
    }
}
