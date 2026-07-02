<?php

namespace App\Repositories\Contracts;

use App\Models\DeliveryMan;

interface DeliveryManRepositoryInterface
{
    public function find(int $id): ?DeliveryMan;

    public function updateStatus(DeliveryMan $deliveryMan, string $status): DeliveryMan;

    /**
     * Atomically add an amount to the rider's outstanding wallet balance
     * (platform owes rider).
     */
    public function creditWallet(DeliveryMan $deliveryMan, string $amount): void;

    public function resetWalletBalance(DeliveryMan $deliveryMan): void;

    /**
     * Atomically add an amount to the rider's held COD cash (rider owes
     * platform).
     */
    public function incrementCashInHand(DeliveryMan $deliveryMan, string $amount): void;

    public function decrementCashInHand(DeliveryMan $deliveryMan, string $amount): void;
}
