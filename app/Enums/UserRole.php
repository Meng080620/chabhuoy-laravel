<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Vendor = 'vendor';
    case Admin = 'admin';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Token abilities granted to this role. Limits the blast radius of a
     * leaked token: it can only do what its role is allowed to do, even if
     * route middleware were ever misconfigured. Admin gets Sanctum's
     * wildcard, which satisfies any `tokenCan` check.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Vendor => ['cart:manage', 'orders:manage', 'vendor:manage'],
            self::Customer => ['cart:manage', 'orders:manage'],
        };
    }
}
