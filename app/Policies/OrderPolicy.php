<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        // Vendors and admins manage the store; ordering is a customer action.
        return ! $user->isVendor();
    }

    public function cancel(User $user, Order $order): bool
    {
        return ($order->user_id === $user->id || $user->isAdmin())
            && ! $order->status->isFinal();
    }
}
