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
    public function __construct(
        public ?int $guideId,
        public int $capacity,
        public string $basePrice,
        public int $minPaymentPct,
    ) {}

    public static function fromTour(Tour $tour, ?TenantConfiguration $configuration): self
    {
        return new self(
            guideId: $tour->default_guide_id,
            capacity: $tour->default_capacity,
            basePrice: (string) $tour->base_price,
            minPaymentPct: $configuration?->min_partial_payment_pct ?? Booking::DEFAULT_MIN_PAYMENT_PCT,
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
        ];
    }
}
