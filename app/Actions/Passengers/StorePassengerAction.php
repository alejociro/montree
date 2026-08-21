<?php

declare(strict_types=1);

namespace App\Actions\Passengers;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingTraveler;

/**
 * Alta de un pasajero sobre una reserva que ya existe. No crea reservas: la
 * venta de mostrador está fuera de alcance.
 */
final class StorePassengerAction
{
    public function __construct(private UpdatePassengerAction $updatePassenger) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Booking $booking, array $attributes): BookingTraveler
    {
        if ($booking->travelers()->count() >= $booking->travelers_count) {
            throw BookingException::travelersComplete($booking->travelers_count);
        }

        return $this->updatePassenger->handle($booking, $booking->travelers()->make(), $attributes);
    }
}
