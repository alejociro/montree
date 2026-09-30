<?php

declare(strict_types=1);

namespace App\Http\Resources\Catalog;

use App\Http\Resources\Tour\TourStopResource;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Services\Catalog\RatingDistribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tour
 */
final class PublicTourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cover = $this->images->firstWhere('is_cover', true) ?? $this->images->first();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'base_price' => $this->base_price,
            // T7: precio "Desde" visible siempre, con o sin fecha elegida —
            // mínimo precio EFECTIVO entre las salidas reservables cargadas
            // por el resolver, o el precio base si no tiene ninguna.
            'from_price' => $this->fromPrice(),
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'category' => $this->whenLoaded('category', fn () => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'icon' => $this->category->icon,
                'image_url' => $this->category->image_url,
            ]),
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'rating_distribution' => RatingDistribution::forTour($this->resource),
            'images' => $this->images->map(fn (TourImage $img) => [
                'id' => $img->id,
                'url' => $img->url,
                'is_cover' => (bool) $img->is_cover,
                'alt_text' => $img->alt_text,
                'display_order' => $img->display_order,
            ])->values(),
            'cover_image_url' => $cover?->url,
            'itinerary' => $this->itineraries->map(fn ($step) => [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'duration_label' => $step->duration_label,
            ])->values(),
            'requirements' => $this->requirements ?? [],
            'includes' => $this->includes ?? [],
            'excludes' => $this->excludes ?? [],
            'stops' => TourStopResource::collection($this->stops)->resolve(),
            'meeting_point' => $this->meeting_point,
            'meeting_latitude' => $this->meeting_latitude,
            'meeting_longitude' => $this->meeting_longitude,
            'future_dates' => $this->dates->map(fn (TourDate $d) => [
                'id' => $d->id,
                'starts_at' => $d->starts_at->toIso8601String(),
                'ends_at' => $d->ends_at?->toIso8601String(),
                'price_override' => $d->price_override,
                'effective_price' => $d->effectivePrice(),
                'capacity_total' => $d->capacity,
                'capacity_booked' => $d->booked_count,
                'available_seats' => max(0, $d->capacity - $d->booked_count),
                'is_full' => $d->booked_count >= $d->capacity,
                'status' => $d->status->value,
                'route' => $this->routeOf($d),
                'guide' => $d->guide === null ? null : ['name' => $d->guide->name],
                'booking_closes_at' => $d->booking_closes_at?->toIso8601String(),
                // T12: el cierre EFECTIVO (propio o por la regla de la
                // agencia), o null cuando no hay ningún límite real que
                // mostrarle al viajero —"hasta la hora de salida" no cuenta.
                'effective_booking_closes_at' => $d->hasBookingDeadline()
                    ? $d->effectiveBookingClosesAt()->toIso8601String()
                    : null,
                // T7: cada salida manda ya su contenido efectivo resuelto —
                // itinerario, incluye/no incluye, requisitos y punto de
                // encuentro— para que la ficha pública no tenga que repetir
                // la lógica de herencia que ya vive en el modelo.
                'effective_itinerary' => $d->effectiveItinerary(),
                'effective_includes' => $d->effectiveIncludes(),
                'effective_excludes' => $d->effectiveExcludes(),
                'effective_requirements' => $d->effectiveRequirements(),
                'effective_meeting_point' => $d->effectiveMeetingPoint(),
            ])->values(),
            'is_favorite' => (bool) ($this->is_favorite ?? false),
        ];
    }

    /**
     * Mínimo precio efectivo entre las salidas cargadas (ya filtradas a
     * reservables por `TourDetailResolver::bySlug()`), o el precio base si
     * el tour no tiene ninguna.
     */
    private function fromPrice(): string
    {
        $min = $this->dates
            ->map(fn (TourDate $d) => (float) $d->effectivePrice())
            ->min();

        return $min === null
            ? (string) $this->base_price
            : number_format($min, 2, '.', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeOf(TourDate $departure): ?array
    {
        $route = $departure->route;

        if (! $route instanceof Route) {
            return null;
        }

        return [
            'id' => $route->id,
            'name' => $route->name,
            'kind' => $route->kind?->value,
            'difficulty' => $route->difficulty?->value,
            'distance_km' => $route->distance_km,
            'duration_hours' => $route->duration_hours,
            'description' => $route->description,
            'stops' => $route->stops->map(fn (RouteStop $stop) => [
                'position' => $stop->position,
                'name' => $stop->name,
                'kind' => $stop->kind->value,
                'time_label' => $stop->time_label,
                'latitude' => $stop->latitude,
                'longitude' => $stop->longitude,
            ])->values(),
        ];
    }
}
