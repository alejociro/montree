<?php

declare(strict_types=1);

namespace App\Http\Resources\Passenger;

use App\Http\Resources\Tour\TourStopResource;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Detalle de tour en modo lectura para el guía (D1): contenido, ruta,
 * itinerario, logística y **sus** salidas.
 *
 * WHY: `my_departures` sale de una colección ya filtrada por el controlador,
 * no de `$tour->dates`. Las salidas de otros guías del mismo tour no se
 * listan, y no hay ningún dato de precio de costo ni acción de escritura.
 *
 * @mixin Tour
 */
final class GuideTourResource extends JsonResource
{
    /**
     * @param  Collection<int, TourDate>  $departures
     */
    public function __construct(Tour $tour, private readonly Collection $departures)
    {
        parent::__construct($tour);
    }

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
            'summary' => $this->short_description,
            'description' => $this->description,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'category' => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'cover_image_url' => $cover?->url,
            'images' => $this->images->map(fn (TourImage $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'is_cover' => (bool) $image->is_cover,
                'alt_text' => $image->alt_text,
            ])->values(),
            'includes' => $this->includes ?? [],
            'excludes' => $this->excludes ?? [],
            'requirements' => $this->requirements ?? [],
            'itinerary' => $this->itineraries->map(fn ($step): array => [
                'step_number' => $step->step_number,
                'title' => $step->title,
                'description' => $step->description,
                'duration_label' => $step->duration_label,
            ])->values(),
            'stops' => TourStopResource::collection($this->stops)->resolve($request),
            'meeting_point' => $this->meeting_point,
            'meeting_latitude' => $this->meeting_latitude,
            'meeting_longitude' => $this->meeting_longitude,
            'my_departures' => $this->departures->map(fn (TourDate $departure): array => [
                'id' => $departure->id,
                'starts_at' => $departure->starts_at->toIso8601String(),
                'ends_at' => $departure->ends_at?->toIso8601String(),
                'capacity' => $departure->capacity,
                'booked_count' => $departure->booked_count,
                'status' => $departure->status->value,
                'passengers_count' => (int) ($departure->passengers_count ?? 0),
            ])->values(),
        ];
    }
}
