<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Actions\Passengers\UpdatePassengerAction;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Support\Facades\DB;

final class SyncBookingTravelersAction
{
    public function __construct(private UpdatePassengerAction $updatePassenger) {}

    /**
     * @param  array<int, array<string, mixed>>  $travelers
     */
    public function handle(Booking $booking, array $travelers): Booking
    {
        if ($booking->isLocked()) {
            throw BookingException::travelersLocked();
        }

        return DB::transaction(function () use ($booking, $travelers): Booking {
            $existing = $booking->travelers()->get()->keyBy('id');
            $keptIds = [];

            foreach ($travelers as $traveler) {
                $id = isset($traveler['id']) ? (int) $traveler['id'] : null;
                $passenger = $id === null ? null : $existing->get($id);

                $keptIds[] = $this->updatePassenger->handle(
                    $booking,
                    $passenger ?? $booking->travelers()->make(),
                    $traveler,
                )->id;
            }

            BookingTraveler::query()
                ->where('booking_id', $booking->id)
                ->whereNotIn('id', $keptIds)
                ->delete();

            return $booking->fresh(['travelers']);
        });
    }
}
