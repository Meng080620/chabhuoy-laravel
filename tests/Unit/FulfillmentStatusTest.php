<?php

namespace Tests\Unit;

use App\Enums\FulfillmentStatus;
use PHPUnit\Framework\TestCase;

/**
 * The per-line fulfillment machine. Guards the rider-return path added for
 * 6amMart parity: a shipped parcel that comes back is a distinct terminal
 * state, not a reuse of Cancelled (which means "never shipped, stock released").
 */
class FulfillmentStatusTest extends TestCase
{
    public function test_a_shipped_line_may_be_returned(): void
    {
        $this->assertTrue(FulfillmentStatus::Shipped->canTransitionTo(FulfillmentStatus::Returned));
    }

    public function test_returned_is_terminal(): void
    {
        $this->assertTrue(FulfillmentStatus::Returned->isFinal());
        $this->assertSame([], FulfillmentStatus::Returned->allowedTransitions());
    }

    public function test_a_pending_line_cannot_jump_straight_to_returned(): void
    {
        // Only a shipped (dispatched) parcel can be returned; a never-shipped
        // line is cancelled instead.
        $this->assertFalse(FulfillmentStatus::Pending->canTransitionTo(FulfillmentStatus::Returned));
    }
}
