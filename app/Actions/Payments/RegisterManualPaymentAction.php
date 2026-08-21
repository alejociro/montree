<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pago recibido fuera de pasarela (efectivo, transferencia): el «Registrar
 * pago» del drawer de la planilla.
 *
 * WHY: la referencia va dentro de `gateway_response` porque `payments` no tiene
 * columna propia para ella y este feature no toca el esquema. El saldo se
 * recalcula sumando a `bookings.paid_amount` dentro de la transacción: el
 * reparto por pasajero se deriva de ahí (D5) y no se guarda en ningún lado.
 */
final class RegisterManualPaymentAction
{
    public function handle(Booking $booking, string $amount, ?string $reference, ?Carbon $paidAt): Booking
    {
        if ($booking->isLocked()) {
            throw BookingException::paymentsLocked();
        }

        return DB::transaction(function () use ($booking, $amount, $reference, $paidAt): Booking {
            $paid = bcadd($booking->paid_amount, $amount, 2);
            $isSettled = bccomp($paid, $booking->total_amount, 2) >= 0;

            Payment::query()->create([
                'booking_id' => $booking->id,
                'gateway' => PaymentGateway::Manual,
                'amount' => $amount,
                'currency' => $booking->currency,
                'type' => $isSettled ? PaymentType::Full : PaymentType::Partial,
                'status' => PaymentStatus::Completed,
                'gateway_response' => ['reference' => $reference],
                'processed_at' => $paidAt ?? now(),
            ]);

            $booking->update([
                'paid_amount' => $paid,
                'status' => $this->status($booking, $isSettled),
                'confirmed_at' => $isSettled ? ($booking->confirmed_at ?? now()) : $booking->confirmed_at,
                'expires_at' => $isSettled ? null : $booking->expires_at,
            ]);

            return $booking->refresh();
        });
    }

    /**
     * Una reserva `completed` no vuelve a `confirmed` porque llegue el saldo;
     * lo que cambia de estado es la que seguía esperando el pago.
     */
    private function status(Booking $booking, bool $isSettled): BookingStatus
    {
        if (! $isSettled || $booking->status !== BookingStatus::PendingPayment) {
            return $booking->status;
        }

        return BookingStatus::Confirmed;
    }
}
