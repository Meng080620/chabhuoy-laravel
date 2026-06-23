<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Customer-facing "we got your order" message.
 *
 * Channel-agnostic by design: via() decides where it goes, so adding Telegram
 * later is a toTelegram() method + a channel entry, with no change to the job
 * that sends it. Not queued itself — the SendOrderConfirmation job already
 * provides the queue + retry envelope, so queuing here would double-wrap it.
 */
class OrderConfirmation extends Notification
{
    public function __construct(public readonly Order $order) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');

        $mail = (new MailMessage)
            ->subject("Order confirmed — {$order->uuid}")
            ->greeting("Thanks, {$notifiable->name}!")
            ->line('We received your order. Here is a summary:');

        foreach ($order->items as $item) {
            $mail->line("• {$item->quantity} × {$item->product_name} — {$item->line_total}");
        }

        return $mail
            ->line("Total: {$order->total}")
            ->line("Status: {$order->status->label()}");
    }
}
