<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\DepartureScope;
use App\Enums\TourDateStatus;
use App\Models\TourDate;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cifras de cabecera y conteos por bandeja del tablero de salidas.
 *
 * WHY: los KPIs describen el estado de la operación completa, así que NO
 * heredan la bandeja ni el buscador —si «Sin guía asignado» cayera a cero al
 * escribir en el buscador dejaría de ser una alarma—. Los conteos de las
 * pestañas sí respetan el buscador y el filtro de tour: son «cuántos de lo que
 * estás mirando hay en cada bandeja».
 *
 * Todo se resuelve con agregados sobre `tour_dates`; el aislamiento por tenant
 * lo pone el global scope de `BelongsToTenant`.
 */
final class DepartureBoardQuery
{
    /**
     * @return array{active:int,seats_left:int,travellers:int,without_guide:int}
     */
    public function stats(): array
    {
        $upcoming = fn (): Builder => TourDate::query()->inScope(DepartureScope::Upcoming);

        $totals = $upcoming()
            ->selectRaw('COUNT(*) as active')
            ->selectRaw('COALESCE(SUM(CASE WHEN capacity > booked_count THEN capacity - booked_count ELSE 0 END), 0) as seats_left')
            ->selectRaw('COALESCE(SUM(booked_count), 0) as travellers')
            ->first();

        return [
            'active' => (int) ($totals?->getAttribute('active') ?? 0),
            'seats_left' => (int) ($totals?->getAttribute('seats_left') ?? 0),
            'travellers' => (int) ($totals?->getAttribute('travellers') ?? 0),
            // `guide_id` es NOT NULL desde la fase 1, así que hoy siempre da
            // cero. El KPI se queda porque la regla puede relajarse y porque un
            // cero explícito es información: no hay salidas huérfanas.
            'without_guide' => (int) $upcoming()->whereNull('guide_id')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counts(?string $search, ?int $tourId): array
    {
        $counts = [];

        foreach (DepartureScope::cases() as $scope) {
            $counts[$scope->value] = $this->base($search, $tourId)->inScope($scope)->count();
        }

        return $counts;
    }

    /**
     * @return Builder<TourDate>
     */
    private function base(?string $search, ?int $tourId): Builder
    {
        return TourDate::query()
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->matchingSearch((string) $search))
            ->when($tourId !== null && $tourId > 0, fn (Builder $query) => $query->where('tour_id', $tourId));
    }

    /**
     * Totales del pie de la tabla, para el corte que el usuario está viendo.
     *
     * @return array{departures:int,travellers:int,seats_left:int}
     */
    public function totalsFor(Builder $query): array
    {
        $totals = (clone $query)
            ->reorder()
            ->selectRaw('COUNT(*) as departures')
            ->selectRaw('COALESCE(SUM(booked_count), 0) as travellers')
            ->selectRaw('COALESCE(SUM(CASE WHEN capacity > booked_count THEN capacity - booked_count ELSE 0 END), 0) as seats_left')
            ->first();

        return [
            'departures' => (int) ($totals?->getAttribute('departures') ?? 0),
            'travellers' => (int) ($totals?->getAttribute('travellers') ?? 0),
            'seats_left' => (int) ($totals?->getAttribute('seats_left') ?? 0),
        ];
    }

    /**
     * Estado al que vuelve una salida rehabilitada: si ya no queda cupo, `full`;
     * si no, `open`. Volver siempre a `open` mentiría sobre la disponibilidad.
     */
    public function statusAfterRestore(TourDate $tourDate): TourDateStatus
    {
        return $tourDate->booked_count >= $tourDate->capacity
            ? TourDateStatus::Full
            : TourDateStatus::Open;
    }
}
