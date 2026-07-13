<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Support\Facades\DB;

final class SyncBookingTravelersAction
{
    private const LOCKED_STATUSES = [BookingStatus::Cancelled, BookingStatus::Expired];

    /**
     * @param  array<int, array<string, mixed>>  $travelers
     */
    public function handle(Booking $booking, array $travelers): Booking
    {
        if (in_array($booking->status, self::LOCKED_STATUSES, true)) {
            throw BookingException::travelersLocked();
        }

        return DB::transaction(function () use ($booking, $travelers): Booking {
            $keptIds = [];

            foreach ($travelers as $traveler) {
                $attributes = [
                    'full_name' => $traveler['full_name'],
                    'is_minor' => (bool) $traveler['is_minor'],
                    'document_type' => $traveler['document_type'] ?? null,
                    'document_number' => $traveler['document_number'] ?? null,
                    'birth_date' => $traveler['birth_date'] ?? null,
                    'nationality' => $traveler['nationality'] ?? null,
                    'email' => $traveler['email'] ?? null,
                    'phone' => $traveler['phone'] ?? null,
                    'dietary_restrictions' => $traveler['dietary_restrictions'] ?? null,
                    'medical_notes' => $traveler['medical_notes'] ?? null,
                ];

                $id = $traveler['id'] ?? null;
                if ($id !== null) {
                    $booking->travelers()->whereKey($id)->update($attributes);
                    $keptIds[] = (int) $id;

                    continue;
                }

                $created = $booking->travelers()->create($attributes);
                $keptIds[] = $created->id;
            }

            BookingTraveler::query()
                ->where('booking_id', $booking->id)
                ->whereNotIn('id', $keptIds)
                ->delete();

            return $booking->fresh(['travelers']);
        });
    }
}
