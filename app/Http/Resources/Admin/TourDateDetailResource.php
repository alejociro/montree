<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\TourDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourDate
 */
final class TourDateDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Código legible que el operador dicta por teléfono. Derivado, no
            // almacenado: viaja en el recurso para que la tabla y el buscador
            // usen exactamente el mismo texto.
            'code' => $this->resource->code(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'available_seats' => max(0, $this->capacity - $this->booked_count),
            'price_override' => $this->price_override,
            'effective_price' => $this->resource->effectivePrice(),
            'min_payment_pct' => $this->min_payment_pct,
            'effective_min_payment_pct' => $this->minPaymentPercentage(),
            'status' => $this->status->value,
            'display_status' => $this->displayStatus()->value,
            'notes' => $this->notes,
            'booking_closes_at' => $this->booking_closes_at?->toIso8601String(),
            // T12: cierre efectivo (propio o por la regla de la agencia),
            // null cuando no hay ningún límite real.
            'effective_booking_closes_at' => $this->resource->hasBookingDeadline()
                ? $this->resource->effectiveBookingClosesAt()->toIso8601String()
                : null,
            // T7: cada bloque viaja como override crudo (`null` = hereda) más
            // el valor efectivo, para que el diálogo precargue con el dato
            // del producto sin perder la marca de "personalizado".
            'itinerary' => $this->itinerary,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'requirements' => $this->requirements,
            'meeting_point' => $this->meeting_point,
            'effective_itinerary' => $this->resource->effectiveItinerary(),
            'effective_includes' => $this->resource->effectiveIncludes(),
            'effective_excludes' => $this->resource->effectiveExcludes(),
            'effective_requirements' => $this->resource->effectiveRequirements(),
            'effective_meeting_point' => $this->resource->effectiveMeetingPoint(),
            'is_customized' => $this->resource->hasCustomContent(),
            'tour' => $this->whenLoaded('tour', fn () => [
                'id' => $this->tour->id,
                'name' => $this->tour->name,
                'slug' => $this->tour->slug,
            ]),
            'guide' => $this->whenLoaded('guide', fn () => $this->guide
                ? ['id' => $this->guide->id, 'name' => $this->guide->name]
                : null),
            'route' => $this->whenLoaded('route', fn () => $this->route
                ? ['id' => $this->route->id, 'name' => $this->route->name]
                : null),
            'provider' => $this->whenLoaded('provider', fn () => $this->provider
                ? ['id' => $this->provider->id, 'name' => $this->provider->name]
                : null),
            'hotels' => $this->whenLoaded('hotels', fn () => $this->hotels
                ->map(fn ($hotel) => ['id' => $hotel->id, 'name' => $hotel->name])
                ->values()),
        ];
    }
}
