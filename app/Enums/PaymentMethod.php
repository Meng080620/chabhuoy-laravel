<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Qr = 'qr';
    case Cod = 'cod';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'Credit / Debit Card',
            self::Qr => 'QR Payment',
            self::Cod => 'Cash on Delivery',
        };
    }

    /**
     * COD is settled on delivery, so no charge is attempted at checkout.
     */
    public function requiresImmediateCapture(): bool
    {
        return $this !== self::Cod;
    }
}
