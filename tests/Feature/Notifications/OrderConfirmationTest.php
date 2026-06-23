<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_notifies_the_ordering_customer(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        (new SendOrderConfirmation($order))->handle();

        Notification::assertSentTo(
            $user,
            OrderConfirmation::class,
            fn (OrderConfirmation $n) => $n->order->is($order),
        );
    }

    public function test_the_mail_summarises_the_order(): void
    {
        $user = User::factory()->create(['name' => 'Dara']);
        $order = Order::factory()->for($user)->create(['total' => '42.00']);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => 'Kampot Pepper',
            'quantity' => 3,
            'line_total' => '12.00',
        ]);

        $mail = (new OrderConfirmation($order))->toMail($user);

        $this->assertSame("Order confirmed — {$order->uuid}", $mail->subject);

        $body = implode("\n", $mail->introLines + $mail->outroLines);
        $this->assertStringContainsString('3 × Kampot Pepper', $body);
        $this->assertStringContainsString('Total: 42.00', $body);
    }

    public function test_it_goes_out_over_the_mail_channel(): void
    {
        $order = Order::factory()->create();

        $this->assertSame(['mail'], (new OrderConfirmation($order))->via($order->user));
    }
}
