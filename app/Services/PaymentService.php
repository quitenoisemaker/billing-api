<?php

namespace App\Services;

use App\Jobs\ProcessPayment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Create a new payment transaction and dispatch the background processing job.
     */
    public function createPayment(User $user, float $amount): Payment
    {
        /** @var Payment $payment */
        $payment = $user->payments()->create([
            'amount' => $amount,
            'status' => 'pending',
            'reference' => 'pay_' . Str::random(12),
        ]);

        // Dispatch background processing job
        ProcessPayment::dispatch($payment);

        return $payment;
    }

    /**
     * Calculate the sum of all successful transactions for the user.
     */
    public function calculateBalance(User $user): float
    {
        return (float) $user->payments()
            ->where('status', 'successful')
            ->sum('amount');
    }

    /**
     * Get the latest payments for a user.
     */
    public function getUserPayments(User $user): Collection
    {
        return $user->payments()->latest()->get();
    }
}
