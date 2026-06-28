<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Cannot move an order from {$from->value} to {$to->value}.",
            previous: $previous,
        );
    }

    /**
     * Render as a clean 422 for API consumers instead of a 500 — mirrors
     * InvalidFulfillmentTransitionException so the contract is consistent.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'status' => [$this->getMessage()],
            ],
        ], 422);
    }
}
