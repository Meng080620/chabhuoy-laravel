<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DeliveryManCashCeilingExceededException extends RuntimeException
{
    public function __construct(public readonly string $ceiling)
    {
        parent::__construct("Accepting this order would put you over the {$ceiling} cash-in-hand limit.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['cod_amount' => [$this->getMessage()]],
        ], 422);
    }
}
