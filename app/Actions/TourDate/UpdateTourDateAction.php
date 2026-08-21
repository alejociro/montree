<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Exceptions\TourDateException;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

final class UpdateTourDateAction
{
    private const ACTIVE_BOOKING_STATUSES = [
        BookingStatus::PendingPayment->value,
        BookingStatus::Confirmed->value,
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(TourDate $tourDate, array $data): TourDate
    {
        if ($tourDate->status === TourDateStatus::Cancelled) {
            throw TourDateException::cancelled();
        }

        if ($this->changesStartsAt($tourDate, $data) && $this->hasActiveBookings($tourDate)) {
            throw TourDateException::hasBookings();
        }

        $tourDate->fill(array_intersect_key($data, array_flip([
            'starts_at', 'capacity', 'price_override', 'notes', 'guide_id', 'route_id', 'provider_id',
        ])));

        // WHY (D9): se rederiva siempre, no solo cuando cambia el inicio. Una
        // salida vieja con un `ends_at` inventado se corrige la primera vez que
        // alguien la toca, en vez de sobrevivir a la migración.
        $tourDate->loadMissing('tour');
        $tourDate->ends_at = TourDate::deriveEndsAt($tourDate->starts_at, $tourDate->tour->duration_hours);

        $tourDate->save();

        if (array_key_exists('hotel_ids', $data)) {
            $tourDate->hotels()->sync($data['hotel_ids'] ?? []);
        }

        return $tourDate->fresh() ?? $tourDate;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changesStartsAt(TourDate $tourDate, array $data): bool
    {
        return array_key_exists('starts_at', $data)
            && Carbon::parse($data['starts_at'])->ne($tourDate->starts_at);
    }

    private function hasActiveBookings(TourDate $tourDate): bool
    {
        return $tourDate->bookings()
            ->whereIn('status', self::ACTIVE_BOOKING_STATUSES)
            ->exists();
    }
}
