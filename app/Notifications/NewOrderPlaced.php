<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrderPlaced extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Order #{$this->order->id} Received")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new order #{$this->order->id}.")
            ->line('Order Type: ' . ucfirst($this->order->type))
            ->line('Total Amount: ' . $this->order->formatted_total)
            ->line('Scheduled For: ' . $this->order->scheduled_for?->format('M j, Y g:i A') ?? 'ASAP')
            ->action('View Order', route('orders.show', $this->order->id))
            ->line('Please prepare the order as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'type' => $this->order->type,
            'total_amount' => $this->order->total_amount,
            'message' => "New order #{$this->order->id} has been placed",
        ];
    }
}
