<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
