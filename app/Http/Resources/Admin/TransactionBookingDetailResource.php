<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * La misma reserva del listado más su dinero: el detalle se abre para conciliar
 * lo cobrado contra lo que falta.
 *
 * @mixin Booking
 */
final class TransactionBookingDetailResource extends TransactionBookingResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'holder_email' => $this->contactField('email') ?? $this->user?->email,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'due_amount' => $this->due_amount,
            'status' => $this->status->value,
        ];
    }
}
