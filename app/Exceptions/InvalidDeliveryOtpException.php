<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidDeliveryOtpException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The delivery OTP does not match.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['otp' => [$this->getMessage()]],
        ], 422);
    }
}
