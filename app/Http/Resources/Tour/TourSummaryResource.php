<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\Tour;
use App\Queries\TourOperationalSummaryQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Tour
 */
class TourSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'status' => $this->status->value,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'duration_hours' => $this->duration_hours,
            'difficulty' => $this->difficulty->value,
            'default_capacity' => $this->default_capacity,
            'category' => $this->whenLoaded('category', fn () => $this->category !== null
                ? (new CategoryResource($this->category))->resolve()
                : null),
            'cover_image_url' => $this->whenLoaded('coverImage', fn () => $this->coverImage?->url),
            'images_count' => $this->whenCounted('images'),
            'bookings_count' => $this->whenCounted('bookings'),
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            $this->mergeWhen($this->hasOperationalSummary(), fn (): array => [
                'operations' => $this->operations(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * WHY: `operations` viaja solo cuando la consulta adjuntó los agregados de
     * `TourOperationalSummaryQuery`. El mismo Resource sirve al selector de
     * promociones, que no los pide: ahí el campo no existe, en vez de ceros que
     * se leerían como «este tour no tiene pasajeros».
     */
    private function hasOperationalSummary(): bool
    {
        return array_key_exists(
            TourOperationalSummaryQuery::PREFIX.'passengers_count',
            $this->resource->getAttributes(),
        );
    }

    /**
     * @return array{
     *     next_departure_at: string|null,
     *     passengers_count: int,
     *     occupancy: array{occupied: int, capacity: int},
     *     passengers_with_due: int,
     * }
     */
    private function operations(): array
    {
        $nextDepartureAt = $this->attribute('next_departure_at');

        return [
            'next_departure_at' => $nextDepartureAt === null
                ? null
                : Carbon::parse((string) $nextDepartureAt)->toIso8601String(),
            'passengers_count' => (int) $this->attribute('passengers_count'),
            'occupancy' => [
                'occupied' => (int) $this->attribute('next_departure_booked'),
                'capacity' => (int) $this->attribute('next_departure_capacity'),
            ],
            'passengers_with_due' => (int) $this->attribute('passengers_with_due'),
        ];
    }

    private function attribute(string $name): mixed
    {
        return $this->resource->getAttributes()[TourOperationalSummaryQuery::PREFIX.$name] ?? null;
    }
}
