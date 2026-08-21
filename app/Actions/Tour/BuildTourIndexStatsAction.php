<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Support\Carbon;

/**
 * KPIs del encabezado del listado de tours del panel.
 *
 * WHY: mismo criterio que `BuildTourShowStatsAction` —salida futura en `open` o
 * `full`, dinero derivado de `bookings` (D5)— para que el detalle y el listado
 * no cuenten la misma cosa de dos maneras. `pending_balance` es dinero de
 * pasajeros: se calcula solo para quien puede verlo y, si no, la clave no
 * viaja; un cero sería una cifra falsa.
 */
final class BuildTourIndexStatsAction
{
    private const UPCOMING_WINDOW_DAYS = 30;

    /**
     * @return array{
     *     tours: array{active: int, draft: int, paused: int, archived: int},
     *     upcoming_departures: array{count: int, next_starts_at: string|null},
     *     occupancy: array{booked_seats: int, total_capacity: int, rate: int},
     *     pending_balance?: array{passengers: int, amount: string, currency: string},
     * }
     */
    public function handle(bool $withPendingBalance): array
    {
        $departures = $this->departures();

        $bookedSeats = (int) ($departures?->getAttribute('booked_seats') ?? 0);
        $totalCapacity = (int) ($departures?->getAttribute('total_capacity') ?? 0);

        $stats = [
            'tours' => $this->toursByStatus(),
            'upcoming_departures' => [
                'count' => (int) ($departures?->getAttribute('window_count') ?? 0),
                'next_starts_at' => $this->iso($departures?->getAttribute('next_starts_at')),
            ],
            'occupancy' => [
                'booked_seats' => $bookedSeats,
                'total_capacity' => $totalCapacity,
                'rate' => $totalCapacity > 0 ? (int) round($bookedSeats / $totalCapacity * 100) : 0,
            ],
        ];

        if (! $withPendingBalance) {
            return $stats;
        }

        return [...$stats, 'pending_balance' => $this->pendingBalance()];
    }

    /**
     * @return array{active: int, draft: int, paused: int, archived: int}
     */
    private function toursByStatus(): array
    {
        $counts = Tour::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'active' => (int) $counts->get(TourStatus::Active->value, 0),
            'draft' => (int) $counts->get(TourStatus::Draft->value, 0),
            'paused' => (int) $counts->get(TourStatus::Paused->value, 0),
            'archived' => (int) $counts->get(TourStatus::Archived->value, 0),
        ];
    }

    /**
     * Una sola consulta para los dos bloques: la próxima salida se busca entre
     * todas las futuras —si la más cercana cae fuera de la ventana, la fecha
     * sigue siendo cierta— y el conteo y la ocupación, solo dentro de los 30
     * días.
     */
    private function departures(): ?TourDate
    {
        $limit = Carbon::now()->addDays(self::UPCOMING_WINDOW_DAYS);

        return TourDate::query()
            ->whereHas('tour')
            ->where('starts_at', '>', Carbon::now())
            ->whereIn('status', [TourDateStatus::Open->value, TourDateStatus::Full->value])
            ->selectRaw('MIN(starts_at) as next_starts_at')
            ->selectRaw('COALESCE(SUM(CASE WHEN starts_at <= ? THEN 1 ELSE 0 END), 0) as window_count', [$limit])
            ->selectRaw('COALESCE(SUM(CASE WHEN starts_at <= ? THEN booked_count ELSE 0 END), 0) as booked_seats', [$limit])
            ->selectRaw('COALESCE(SUM(CASE WHEN starts_at <= ? THEN capacity ELSE 0 END), 0) as total_capacity', [$limit])
            ->first();
    }

    /**
     * @return array{passengers: int, amount: string, currency: string}
     */
    private function pendingBalance(): array
    {
        $balance = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->selectRaw('COALESCE(SUM(travelers_count), 0) as passengers')
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as amount')
            ->first();

        return [
            'passengers' => (int) ($balance?->getAttribute('passengers') ?? 0),
            'amount' => number_format((float) ($balance?->getAttribute('amount') ?? 0), 2, '.', ''),
            'currency' => (string) (Tenant::current()?->configuration?->currency ?? 'USD'),
        ];
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse((string) $value)->toIso8601String();
    }
}
