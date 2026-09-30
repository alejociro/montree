<?php

declare(strict_types=1);

namespace App\Actions\TourDate;

use App\Enums\TourDateStatus;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

final class CreateTourDateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tour $tour, array $data): TourDate
    {
        $startsAt = Carbon::parse($data['starts_at']);

        $tourDate = $tour->dates()->create([
            // T8 (revierte D7): sin guía propuesto ni elegido, la clave ni
            // siquiera llega a `validated()` — la salida se crea «por
            // asignar».
            'guide_id' => $data['guide_id'] ?? null,
            'route_id' => $data['route_id'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'starts_at' => $startsAt,
            // WHY (D9): el fin sale de la duración del tour, no del cliente.
            'ends_at' => TourDate::deriveEndsAt($startsAt, $tour->duration_hours),
            'capacity' => $data['capacity'],
            'price_override' => $data['price_override'] ?? null,
            'min_payment_pct' => $data['min_payment_pct'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => TourDateStatus::Open,
            'booked_count' => 0,
            // T7: apagado en el diálogo → no llega la clave → hereda del
            // producto. `array_key_exists` distingue "no llegó" de "llegó
            // como null explícito", pero acá las dos cosas heredan igual.
            'itinerary' => $data['itinerary'] ?? null,
            'includes' => $data['includes'] ?? null,
            'excludes' => $data['excludes'] ?? null,
            'requirements' => $data['requirements'] ?? null,
            'meeting_point' => $data['meeting_point'] ?? null,
            'booking_closes_at' => $data['booking_closes_at'] ?? null,
        ]);

        if (! empty($data['hotel_ids'])) {
            $tourDate->hotels()->sync($data['hotel_ids']);
        }

        return $tourDate;
    }
}
