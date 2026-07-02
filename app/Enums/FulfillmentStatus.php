<?php

namespace App\Enums;

/**
 * Per-line fulfillment state.
 *
 * An order can span multiple vendors, so shipping/delivery is tracked on each
 * order_item — not the order. The order-level {@see OrderStatus} is a rollup
 * derived from these line statuses (see OrderService::syncOrderStatusFromLines).
 */
enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    // A shipped parcel that came back undeliverable (rider Return path). Its own
    // terminal state — distinct from Cancelled, which means "never shipped, stock
    // released" — because Returned goods were dispatched and physically restocked.
    case Returned = 'returned';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether this line may legally move into the given status.
     * Centralising the machine keeps transition rules out of controllers.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered, self::Returned],
            self::Delivered, self::Cancelled, self::Returned => [],
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Returned], true);
    }
}
