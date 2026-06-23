<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->isVendor() && $user->vendor?->isActive();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->isAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->owns($user, $product) || $user->isAdmin();
    }

    private function owns(User $user, Product $product): bool
    {
        return $user->isVendor() && $user->vendor?->id === $product->vendor_id;
    }
}
