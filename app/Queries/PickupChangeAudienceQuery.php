<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

/**
 * A quién le cambia el plan si se mueve la parada de recogida (regla 6 del
 * handoff): las reservas vivas de salidas que todavía no han ocurrido.
 *
 * WHY: una sola definición para las dos caras de la regla — el aviso que la UI
 * muestra ANTES de guardar («notifica por correo a N pasajeros») y el reparto
 * real de la notificación DESPUÉS de guardar. Si se contaran por separado, el
 * número prometido y los correos enviados podrían no coincidir.
 */
final class PickupChangeAudienceQuery
{
    /**
     * @return Builder<Booking>
     */
    public function bookings(Tour $tour): Builder
    {
        return Booking::query()
            ->where('tour_id', $tour->id)
            ->whereIn('status', BookingStatus::activeValues())
            ->whereHas('tourDate', fn (Builder $query) => $query->where('starts_at', '>', now()));
    }

    /**
     * Cuántas reservas y cuántas personas quedarían notificadas ahora mismo.
     *
     * @return array{bookings: int, passengers: int}
     */
    public function impact(Tour $tour): array
    {
        $row = $this->bookings($tour)
            ->toBase()
            ->selectRaw('COUNT(*) as bookings_count, COALESCE(SUM(travelers_count), 0) as passengers_count')
            ->first();

        return [
            'bookings' => (int) ($row->bookings_count ?? 0),
            'passengers' => (int) ($row->passengers_count ?? 0),
        ];
    }
}
