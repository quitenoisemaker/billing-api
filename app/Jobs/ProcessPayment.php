<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Events\PaymentStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Payment $payment)
    {
        //
    }

    public function handle(): void
    {
        // Simulate payment processing latency (2 seconds)
        sleep(2);

        // Randomize payment result:
        // 70% successful, 20% failed, 10% refunded
        $rand = rand(1, 100);

        if ($rand <= 70) {
            $status = 'successful';
        } elseif ($rand <= 90) {
            $status = 'failed';
        } else {
            $status = 'refunded';
        }

        $this->payment->update([
            'status' => $status
        ]);

        // Dispatch status changed broadcast event
        PaymentStatusChanged::dispatch($this->payment);
    }
}
