<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\BookingSettlementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RegisterManualPaymentAction
{
    public function __construct(private BookingSettlementService $settlement) {}

    public function handle(Booking $booking, string $amount, ?string $reference, ?Carbon $paidAt): Booking
    {
        if ($booking->isLocked()) {
            throw BookingException::paymentsLocked();
        }

        return DB::transaction(function () use ($booking, $amount, $reference, $paidAt): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::query()->create([
                'booking_id' => $locked->id,
                'gateway' => PaymentGateway::Manual,
                'amount' => $amount,
                'currency' => $locked->currency,
                'type' => $this->type($locked, $amount),
                'status' => PaymentStatus::Completed,
                'reference' => $reference,
                'gateway_response' => ['reference' => $reference],
                'processed_at' => $paidAt ?? now(),
            ]);

            return $this->settlement->apply($locked, $payment)->booking;
        });
    }

    private function type(Booking $booking, string $amount): PaymentType
    {
        $paid = bcadd($booking->paid_amount, $amount, 2);

        return bccomp($paid, $booking->total_amount, 2) >= 0 ? PaymentType::Full : PaymentType::Partial;
    }
}
