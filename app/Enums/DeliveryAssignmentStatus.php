<?php

namespace App\Enums;

/**
 * Lifecycle of a single (order, vendor) parcel handed off to a rider.
 *
 * Created Available the moment a vendor's lines move to Shipped (see
 * OrderService + OrderLineShipped). A rider Accepts, marks PickedUp at the
 * vendor, then Delivered — mirroring FulfillmentStatus's shape but as its own
 * enum, since assignment state (who's carrying it) is a distinct concern from
 * fulfillment state (has the customer received it).
 */
enum DeliveryAssignmentStatus: string
{
    case Available = 'available';
    case Accepted = 'accepted';
    case PickedUp = 'picked_up';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Accepted => 'Accepted',
            self::PickedUp => 'Picked Up',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::Accepted, self::Cancelled],
            self::Accepted => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::Delivered, self::Returned],
            self::Delivered, self::Cancelled, self::Returned => [],
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Returned], true);
    }
}
