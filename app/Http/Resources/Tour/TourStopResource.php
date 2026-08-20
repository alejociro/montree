<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\TourStop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourStop
 */
final class TourStopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind->value,
            'code' => $this->code,
            'label' => $this->label,
            'name' => $this->name,
            'place' => $this->place,
            'time' => $this->time_label,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'itinerary_step' => $this->itinerary_step,
        ];
    }
}
