<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class DeliveryManOutsideServiceZoneException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You are outside the service area. Move closer to accept this order.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['lat' => [$this->getMessage()]],
        ], 422);
    }
}
