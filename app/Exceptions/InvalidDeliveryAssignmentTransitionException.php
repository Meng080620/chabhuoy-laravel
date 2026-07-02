<?php

namespace App\Exceptions;

use App\Enums\DeliveryAssignmentStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InvalidDeliveryAssignmentTransitionException extends RuntimeException
{
    public function __construct(
        public readonly DeliveryAssignmentStatus $from,
        public readonly DeliveryAssignmentStatus $to,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Cannot move a delivery assignment from {$from->value} to {$to->value}.",
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
