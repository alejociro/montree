<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Booking;
use App\Models\TenantConfiguration;
use App\Models\Tour;

/**
 * Lo que una salida nueva hereda del producto y de la agencia (spec §G).
 *
 * `base_price` viaja como referencia visible, no como valor del formulario: la
 * salida solo guarda `price_override` cuando el operador decide apartarse.
 */
final readonly class DepartureDefaults
{
    /**
     * @param  array<int, array<string, mixed>>  $itinerary
     * @param  array<int, string>  $includes
     * @param  array<int, string>  $excludes
     * @param  array<int, string>  $requirements
     */
    public function __construct(
        public ?int $guideId,
        public int $capacity,
        public string $basePrice,
        public int $minPaymentPct,
        // T12: regla de cierre de reservas de la agencia (horas antes del
        // inicio); null = sin regla. Referencia visible para el paso
        // "Producto y fecha" cuando la salida no tiene su propio cierre.
        public ?int $bookingAdvanceHours,
        public array $itinerary,
        public array $includes,
        public array $excludes,
        public array $requirements,
        public ?string $meetingPoint,
    ) {}

    public static function fromTour(Tour $tour, ?TenantConfiguration $configuration): self
    {
        return new self(
            guideId: $tour->default_guide_id,
            capacity: $tour->default_capacity,
            basePrice: (string) $tour->base_price,
            minPaymentPct: $configuration?->min_partial_payment_pct ?? Booking::DEFAULT_MIN_PAYMENT_PCT,
            bookingAdvanceHours: $configuration?->booking_advance_hours,
            // T7: lo que la sección "Contenido de la salida" precarga como
            // referencia mientras el interruptor de cada bloque está apagado.
            itinerary: $tour->itineraries->map(fn ($step) => [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'duration_label' => $step->duration_label,
            ])->values()->all(),
            includes: $tour->includes ?? [],
            excludes: $tour->excludes ?? [],
            requirements: $tour->requirements ?? [],
            meetingPoint: $tour->meeting_point,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guide_id' => $this->guideId,
            'capacity' => $this->capacity,
            'base_price' => $this->basePrice,
            'min_payment_pct' => $this->minPaymentPct,
            'booking_advance_hours' => $this->bookingAdvanceHours,
            'itinerary' => $this->itinerary,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'requirements' => $this->requirements,
            'meeting_point' => $this->meetingPoint,
        ];
    }
}
