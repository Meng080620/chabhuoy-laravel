<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DeliveryManOfflineException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You must go online before accepting an order.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['is_online' => [$this->getMessage()]],
        ], 422);
    }
}
