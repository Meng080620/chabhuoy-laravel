<?php

namespace App\Repositories\Eloquent;

use App\Models\DeliveryMan;
use App\Repositories\Contracts\DeliveryManRepositoryInterface;

class EloquentDeliveryManRepository implements DeliveryManRepositoryInterface
{
    public function find(int $id): ?DeliveryMan
    {
        return DeliveryMan::find($id);
    }

    public function updateStatus(DeliveryMan $deliveryMan, string $status): DeliveryMan
    {
        $deliveryMan->update(['status' => $status]);

        return $deliveryMan->refresh();
    }

    public function creditWallet(DeliveryMan $deliveryMan, string $amount): void
    {
        // Atomic column-level increment avoids a read-modify-write race when
        // multiple deliveries settle for the same rider concurrently.
        $deliveryMan->increment('wallet_balance', $amount);
    }

    public function resetWalletBalance(DeliveryMan $deliveryMan): void
    {
        $deliveryMan->update(['wallet_balance' => 0]);
    }

    public function incrementCashInHand(DeliveryMan $deliveryMan, string $amount): void
    {
        $deliveryMan->increment('cash_in_hand', $amount);
    }

    public function decrementCashInHand(DeliveryMan $deliveryMan, string $amount): void
    {
        $deliveryMan->decrement('cash_in_hand', $amount);
    }
}
