<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TourDateStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Cifras operativas del listado de tours: próxima salida, pasajeros, ocupación
 * y saldos, más los órdenes que se apoyan en ellas.
 *
 * WHY: todo va por subconsultas correlacionadas colgadas del `select`, no por
 * relaciones cargadas fila a fila. El listado dispara el mismo número de
 * consultas con 3 tours que con 300 (`TourIndexOperationsQueryCountTest`).
 * El criterio de «próxima salida» y el de «pago completado» son los de
 * `BuildTourShowStatsAction`; el saldo del pasajero se deriva del saldo de su
 * reserva (D5), no de una columna.
 */
final class TourOperationalSummaryQuery
{
    /** Prefijo de los atributos calculados; no colisiona con ninguna columna de `tours`. */
    public const PREFIX = 'ops_';

    /**
     * @param  Builder<Tour>  $query
     * @return Builder<Tour>
     */
    public function applyTo(Builder $query): Builder
    {
        return $query
            ->select('tours.*')
            ->addSelect([
                self::PREFIX.'next_departure_at' => $this->nextDeparture('starts_at'),
                self::PREFIX.'next_departure_booked' => $this->nextDeparture('booked_count'),
                self::PREFIX.'next_departure_capacity' => $this->nextDeparture('capacity'),
                self::PREFIX.'passengers_count' => $this->passengers(false),
                self::PREFIX.'passengers_with_due' => $this->passengers(true),
            ]);
    }

    /**
     * Ocupación de la próxima salida como fracción, para ordenar. Una salida sin
     * cupo declarado no puede dividir: cuenta como vacía.
     *
     * @return Builder<TourDate>
     */
    public function occupancyRate(): Builder
    {
        return $this->upcomingDepartures()
            ->selectRaw('CASE WHEN capacity > 0 THEN (booked_count * 1.0) / capacity ELSE 0 END')
            ->limit(1);
    }

    /**
     * Ingresos cobrados del tour: pagos completados de sus reservas, el mismo
     * criterio que `BuildTourShowStatsAction`.
     */
    public function revenue(): \Illuminate\Database\Query\Builder
    {
        return Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->whereNull('bookings.deleted_at')
            ->where('payments.status', PaymentStatus::Completed->value)
            ->whereColumn('bookings.tour_id', 'tours.id')
            ->selectRaw('COALESCE(SUM(payments.amount), 0)')
            ->toBase();
    }

    /**
     * @return Builder<TourDate>
     */
    public function nextDeparture(string $column): Builder
    {
        return $this->upcomingDepartures()->select($column)->limit(1);
    }

    /**
     * @return Builder<TourDate>
     */
    private function upcomingDepartures(): Builder
    {
        return TourDate::query()
            ->whereColumn('tour_dates.tour_id', 'tours.id')
            ->where('tour_dates.starts_at', '>', Carbon::now())
            ->whereIn('tour_dates.status', [TourDateStatus::Open->value, TourDateStatus::Full->value])
            ->orderBy('tour_dates.starts_at');
    }

    /**
     * Viajeros de la próxima salida. `$onlyWithDue` los estrecha a los de una
     * reserva que aún debe: el saldo es de la reserva, no de la persona (D5).
     *
     * @return Builder<Booking>
     */
    private function passengers(bool $onlyWithDue): Builder
    {
        return Booking::query()
            ->selectRaw('COALESCE(SUM(travelers_count), 0)')
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])
            ->when($onlyWithDue, fn (Builder $query): Builder => $query->whereColumn('paid_amount', '<', 'total_amount'))
            ->whereIn('tour_date_id', $this->nextDeparture('id'));
    }

    /**
     * @return array<string, Builder<TourDate>|\Illuminate\Database\Query\Builder>
     */
    public function sortableExpressions(): array
    {
        return [
            'next_departure' => $this->nextDeparture('starts_at'),
            'occupancy' => $this->occupancyRate(),
            'revenue' => $this->revenue(),
        ];
    }
}
