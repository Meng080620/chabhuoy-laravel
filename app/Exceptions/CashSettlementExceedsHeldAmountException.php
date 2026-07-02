<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class CashSettlementExceedsHeldAmountException extends RuntimeException
{
    public function __construct(public readonly string $held)
    {
        parent::__construct("You can't settle more than the {$held} you currently hold.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['amount' => [$this->getMessage()]],
        ], 422);
    }
}
