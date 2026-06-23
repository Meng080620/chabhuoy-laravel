<?php

namespace App\Exceptions;

use App\Enums\FulfillmentStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InvalidFulfillmentTransitionException extends RuntimeException
{
    public function __construct(
        public readonly FulfillmentStatus $from,
        public readonly FulfillmentStatus $to,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Cannot move a line from {$from->value} to {$to->value}.",
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
                'status' => [$this->getMessage()],
            ],
        ], 422);
    }
}
