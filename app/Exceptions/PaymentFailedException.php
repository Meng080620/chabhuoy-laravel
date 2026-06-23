<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PaymentFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Payment could not be processed.',
        public readonly ?string $gatewayCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'gateway_code' => $this->gatewayCode,
        ], 402); // 402 Payment Required
    }
}
