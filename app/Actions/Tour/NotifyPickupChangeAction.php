<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\TourStopKind;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\TourStop;
use App\Notifications\PickupPointChangedNotification;
use App\Queries\PickupChangeAudienceQuery;
use Illuminate\Support\Facades\DB;

/**
 * Regla 6 del handoff: cambiar la parada de recogida de un tour con reservas
 * vivas avisa por correo a los pasajeros afectados.
 *
 * Se dispara desde `SyncTourStopsAction`, que es por donde pasan las paradas en
 * los dos caminos que las escriben. Al crear un tour no hay reservas, así que
 * ahí no cuesta nada.
 */
final class NotifyPickupChangeAction
{
    public function __construct(private PickupChangeAudienceQuery $audience) {}

    /**
     * Foto de la parada de recogida tal como está guardada ahora mismo. `null`
     * cuando el tour no tiene ninguna.
     *
     * @return array{name: string, place: string|null, time_label: string|null, latitude: string, longitude: string}|null
     */
    public function snapshot(Tour $tour): ?array
    {
        $stop = $tour->stops()
            ->where('kind', TourStopKind::Pickup->value)
            ->first();

        return $stop instanceof TourStop ? $this->describe($stop) : null;
    }

    /**
     * @param  array<string, mixed>|null  $before  la foto tomada antes de escribir
     */
    public function handle(Tour $tour, ?array $before): void
    {
        $after = $this->snapshot($tour);

        if ($before === $after) {
            return;
        }

        $previousLabel = $this->label($before);
        $currentLabel = $this->label($after);

        // WHY: las paradas se escriben dentro de la transacción de
        // `UpdateTourAction`. Encolar aquí mismo dejaría que el worker leyera
        // el tour antes del commit —o que saliera un correo por un cambio que
        // terminó revertido.
        DB::afterCommit(function () use ($tour, $previousLabel, $currentLabel): void {
            $this->audience->bookings($tour)
                ->with(['user', 'tour', 'tourDate'])
                ->chunkById(100, function ($bookings) use ($previousLabel, $currentLabel): void {
                    foreach ($bookings as $booking) {
                        $this->notify($booking, $previousLabel, $currentLabel);
                    }
                });
        });
    }

    private function notify(Booking $booking, ?string $previous, ?string $current): void
    {
        $booking->user?->notify(
            PickupPointChangedNotification::fromBooking($booking, $previous, $current),
        );
    }

    /**
     * @return array{name: string, place: string|null, time_label: string|null, latitude: string, longitude: string}
     */
    private function describe(TourStop $stop): array
    {
        return [
            'name' => (string) $stop->name,
            'place' => $stop->place,
            'time_label' => $stop->time_label,
            // Los decimales van tal como los guarda la columna: comparar
            // `4.6` con `4.6000000` marcaría un cambio que no existe.
            'latitude' => (string) $stop->latitude,
            'longitude' => (string) $stop->longitude,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stop
     */
    private function label(?array $stop): ?string
    {
        if ($stop === null) {
            return null;
        }

        $parts = array_filter([
            (string) $stop['name'],
            $stop['place'] === null ? null : (string) $stop['place'],
            $stop['time_label'] === null ? null : (string) $stop['time_label'],
        ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode(' · ', $parts);
    }
}
