<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProcessPaymentAction
{
    /**
     * MVP implementation: simulates a successful gateway charge (no Stripe yet).
     * Future F007.2 will swap in a real Stripe PaymentIntent flow.
     *
     * @param  array<string, mixed>  $data  expected: type=full|partial, amount(optional override)
     */
    public function handle(Booking $booking, array $data): Payment
    {
        return DB::transaction(function () use ($booking, $data): Payment {
            $type = PaymentType::from($data['type'] ?? PaymentType::Full->value);
            $amount = $type === PaymentType::Partial
                ? (string) ($data['amount'] ?? bcdiv($booking->total_amount, '2', 2))
                : $booking->total_amount;

            $payment = Payment::query()->create([
                'booking_id' => $booking->id,
                'gateway' => PaymentGateway::Manual,
                'gateway_payment_id' => 'mock_'.Str::random(24),
                'amount' => $amount,
                'currency' => $booking->currency,
                'type' => $type,
                'status' => PaymentStatus::Completed,
                'gateway_response' => ['simulated' => true, 'timestamp' => now()->toIso8601String()],
                'processed_at' => now(),
            ]);

            $paid = bcadd($booking->paid_amount, $amount, 2);
            $isFull = bccomp($paid, $booking->total_amount, 2) >= 0;

            $booking->update([
                'paid_amount' => $paid,
                'payment_type' => $type,
                'status' => $isFull ? BookingStatus::Confirmed : BookingStatus::PendingPayment,
                'confirmed_at' => $isFull ? now() : $booking->confirmed_at,
                'expires_at' => $isFull ? null : $booking->expires_at,
            ]);

            if ($isFull) {
                $booking->user->notify(BookingConfirmedNotification::fromBooking($booking->fresh()));
            }

            return $payment->fresh();
        });
    }
}
