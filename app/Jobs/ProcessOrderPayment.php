<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 15];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
        public array $paymentData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $payment = Payment::create([
                'order_id' => $this->order->id,
                'amount' => $this->order->total_amount,
                'method' => $this->paymentData['method'],
                'status' => 'completed',
                'transaction_id' => $this->paymentData['transaction_id'] ?? null,
            ]);

            $this->order->update(['status' => 'paid']);

            Log::info('Payment processed successfully', [
                'order_id' => $this->order->id,
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
