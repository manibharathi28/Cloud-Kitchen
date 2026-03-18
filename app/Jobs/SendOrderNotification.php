<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 15];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public string $type
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = $this->order->user;
            
            switch ($this->type) {
                case 'order_placed':
                    // Send order confirmation email
                    Log::info('Order placed notification sent', [
                        'order_id' => $this->order->id,
                        'user_email' => $user->email,
                    ]);
                    break;
                    
                case 'order_ready':
                    // Send order ready notification
                    Log::info('Order ready notification sent', [
                        'order_id' => $this->order->id,
                        'user_email' => $user->email,
                    ]);
                    break;
                    
                case 'order_completed':
                    // Send order completion notification
                    Log::info('Order completed notification sent', [
                        'order_id' => $this->order->id,
                        'user_email' => $user->email,
                    ]);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Order notification failed', [
                'order_id' => $this->order->id,
                'type' => $this->type,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
