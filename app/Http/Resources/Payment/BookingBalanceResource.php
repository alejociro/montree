<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saldo de la reserva después de registrar un pago. El drawer de la planilla
 * lo usa para repintar el estado sin recargar la tabla entera.
 *
 * @mixin Booking
 */
final class BookingBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'booking_number' => $this->booking_number,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'status' => $this->status->value,
        ];
    }
}
