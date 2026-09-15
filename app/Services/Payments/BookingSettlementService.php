<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Data\SettlementResult;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;

/**
 * Asienta un pago `completed` sobre su reserva. Es la única regla de dinero que
 * comparten la pasarela y el efectivo del guía: si vivieran en cada Action, una
 * de las dos se quedaría vieja (ya pasó).
 *
 * La reserva llega bloqueada con `lockForUpdate()` desde la transacción del
 * caller; acá no se abre ninguna.
 */
final class BookingSettlementService
{
    public function apply(Booking $booking, Payment $payment): SettlementResult
    {
        $paid = bcadd($booking->paid_amount, $payment->amount, 2);
        $isSettled = bccomp($paid, $booking->total_amount, 2) >= 0;
        $coversDeposit = bccomp($paid, $booking->deposit_amount, 2) >= 0;
        $securesSeat = $isSettled || $coversDeposit;

        // WHY: una reserva `completed` (o ya `confirmed`) no retrocede porque
        // llegue el saldo; la que cambia de estado es la que seguía esperando.
        $wasJustConfirmed = $securesSeat && $booking->status === BookingStatus::PendingPayment;

        $booking->update([
            'paid_amount' => $paid,
            'payment_type' => $payment->type,
            'status' => $wasJustConfirmed ? BookingStatus::Confirmed : $booking->status,
            'confirmed_at' => $securesSeat ? ($booking->confirmed_at ?? now()) : $booking->confirmed_at,
            'expires_at' => $securesSeat ? null : $booking->expires_at,
        ]);

        return new SettlementResult($booking->refresh(), $wasJustConfirmed);
    }
}
