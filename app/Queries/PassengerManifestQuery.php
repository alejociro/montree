<?php

declare(strict_types=1);

namespace App\Queries;

use App\Data\PassengerManifest;
use App\Data\PassengerManifestFilters;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\TourDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * La consulta que comparten las dos zonas de la planilla (panel y guía) y sus
 * dos exportadores.
 *
 * WHY: una reserva sin viajeros cargados también es una fila —el guía que no
 * ve a esa persona en la lista la deja fuera del vehículo—, así que el
 * resultado no sale de una sola consulta paginada sobre `booking_travelers`:
 * son dos consultas acotadas (viajeros + reservas huérfanas) que se mezclan en
 * memoria. El segmento de saldo se decide sobre dinero derivado (D5), que no es
 * columna, y por eso se resuelve aquí y no en SQL.
 */
final class PassengerManifestQuery
{
    /**
     * @param  Collection<int, TourDate>  $departures  las salidas en alcance; `$tourDateId`
     *                                                 la estrecha a una sola sin perder el
     *                                                 selector que pinta el `meta`.
     */
    public function handle(Collection $departures, ?int $tourDateId, PassengerManifestFilters $filters): PassengerManifest
    {
        $tourDateIds = $tourDateId === null
            ? $departures->pluck('id')->all()
            : [$tourDateId];

        $rows = $this->rows($tourDateIds, $filters);

        return new PassengerManifest($rows, $this->summarize($rows, $filters->fallbackCurrency));
    }

    /**
     * @param  array<int, int>  $tourDateIds
     * @return Collection<int, BookingTraveler>
     */
    private function rows(array $tourDateIds, PassengerManifestFilters $filters): Collection
    {
        return $this->travelers($tourDateIds, $filters)
            ->concat($this->placeholders($tourDateIds, $filters))
            ->filter(fn (BookingTraveler $passenger): bool => $this->matchesSegment($passenger, $filters->segment))
            ->sortBy(fn (BookingTraveler $passenger): string => mb_strtolower((string) $passenger->full_name))
            ->values();
    }

    /**
     * @param  array<int, int>  $tourDateIds
     * @return Collection<int, BookingTraveler>
     */
    private function travelers(array $tourDateIds, PassengerManifestFilters $filters): Collection
    {
        return BookingTraveler::query()
            ->whereHas('booking', $this->bookingScope($tourDateIds, $filters))
            ->search($filters->search)
            ->with(['booking' => fn ($query) => $query->with(['user:id,name', 'tourDate:id,starts_at'])])
            ->get();
    }

    /**
     * @param  array<int, int>  $tourDateIds
     * @return Collection<int, BookingTraveler>
     */
    private function placeholders(array $tourDateIds, PassengerManifestFilters $filters): Collection
    {
        $bookings = Booking::query()
            ->where($this->bookingScope($tourDateIds, $filters))
            ->whereDoesntHave('travelers')
            ->with(['user:id,name', 'tourDate:id,starts_at'])
            ->get();

        return $bookings
            ->map(fn (Booking $booking): BookingTraveler => $this->placeholder($booking))
            ->filter(fn (BookingTraveler $passenger): bool => $this->matchesSearch($passenger, $filters->search));
    }

    /**
     * @param  array<int, int>  $tourDateIds
     * @return \Closure(Builder<Booking>): void
     */
    private function bookingScope(array $tourDateIds, PassengerManifestFilters $filters): \Closure
    {
        return function (Builder $query) use ($tourDateIds, $filters): void {
            $query->whereIn('tour_date_id', $tourDateIds)
                ->whereIn('status', $filters->statusValues());
        };
    }

    /**
     * Fila de marcador de posición: la reserva existe, sus viajeros no se han
     * cargado. El titular es lo único que se sabe de quien va a subir.
     */
    private function placeholder(Booking $booking): BookingTraveler
    {
        $placeholder = new BookingTraveler([
            'full_name' => $booking->user?->name ?? (string) ($booking->contact_snapshot['name'] ?? ''),
        ]);

        $placeholder->setRelation('booking', $booking);

        return $placeholder;
    }

    private function matchesSearch(BookingTraveler $passenger, ?string $search): bool
    {
        $term = trim((string) $search);

        if ($term === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) $passenger->full_name), mb_strtolower($term));
    }

    private function matchesSegment(BookingTraveler $passenger, string $segment): bool
    {
        return match ($segment) {
            'due' => $this->paymentStatus($passenger) === 'due',
            'paid' => $this->paymentStatus($passenger) === 'paid',
            'obs' => trim((string) $passenger->medical_notes) !== '',
            default => true,
        };
    }

    private function paymentStatus(BookingTraveler $passenger): string
    {
        return (string) ($passenger->booking?->passengerShare()['status'] ?? 'paid');
    }

    /**
     * @param  Collection<int, BookingTraveler>  $rows
     * @return array{total_passengers: int, with_due: int, paid: int, with_notes: int, total_due_amount: string, currency: string}
     */
    private function summarize(Collection $rows, string $fallbackCurrency): array
    {
        $withDue = $rows->filter(fn (BookingTraveler $passenger): bool => $this->paymentStatus($passenger) === 'due');

        $totalDue = $withDue->sum(
            fn (BookingTraveler $passenger): float => (float) ($passenger->booking?->passengerShare()['due_amount'] ?? 0),
        );

        return [
            'total_passengers' => $rows->count(),
            'with_due' => $withDue->count(),
            'paid' => $rows->count() - $withDue->count(),
            'with_notes' => $rows->filter(
                fn (BookingTraveler $passenger): bool => trim((string) $passenger->medical_notes) !== '',
            )->count(),
            'total_due_amount' => number_format(round($totalDue, 2), 2, '.', ''),
            'currency' => (string) ($rows->first()?->booking?->currency ?? $fallbackCurrency),
        ];
    }
}
