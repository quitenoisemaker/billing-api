<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $paymentId;
    public string $status;
    public string $timestamp;
    public int $customerId;

    public function __construct(Payment $payment)
    {
        $this->paymentId = $payment->id;
        $this->status = $payment->status;
        $this->timestamp = $payment->updated_at?->toIso8601String() ?? now()->toIso8601String();
        $this->customerId = $payment->user_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('customer.' . $this->customerId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payment.status.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'paymentId' => $this->paymentId,
            'status' => $this->status,
            'timestamp' => $this->timestamp,
            'customerId' => $this->customerId,
        ];
    }
}
