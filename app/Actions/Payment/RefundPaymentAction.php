<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class RefundPaymentAction
{
    public function handle(Payment $payment, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            $payment->update([
                'status' => PaymentStatus::Refunded,
                'refunded_amount' => $payment->amount,
                'refund_reason' => $reason,
                'refunded_at' => now(),
            ]);

            $payment->booking->update([
                'status' => BookingStatus::Refunded,
                'cancelled_at' => now(),
            ]);

            return $payment->fresh();
        });
    }
}
