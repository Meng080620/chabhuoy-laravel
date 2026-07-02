<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DeliveryManOverCapacityException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("You already have {$limit} order(s) in progress — finish one before accepting another.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['status' => [$this->getMessage()]],
        ], 422);
    }
}
