<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Route
 */
final class RouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind?->value,
            'difficulty' => $this->difficulty?->value,
            'start_point' => $this->start_point,
            'start_latitude' => $this->start_latitude,
            'start_longitude' => $this->start_longitude,
            'end_point' => $this->end_point,
            'end_latitude' => $this->end_latitude,
            'end_longitude' => $this->end_longitude,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'distance_km' => $this->distance_km,
            'duration_hours' => $this->duration_hours,
            'max_altitude_m' => $this->max_altitude_m,
            'elevation_gain_m' => $this->elevation_gain_m,
            'group_capacity' => $this->group_capacity,
            'seasons' => $this->seasons ?? [],
            'safety_notes' => $this->safety_notes,
            'required_gear' => $this->required_gear ?? [],
            'permits' => $this->permits,
            'emergency_contact' => $this->emergency_contact,
            'stops' => RouteStopResource::collection($this->whenLoaded('stops', fn () => $this->stops, collect()))->resolve(),
            'tour_dates_count' => (int) ($this->tour_dates_count ?? 0),
        ];
    }
}
