<?php

declare(strict_types=1);

namespace App\Http\Resources\Tour;

use App\Models\TourItinerary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TourItinerary
 */
class TourItineraryStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_number' => $this->step_number,
            'title' => $this->title,
            'description' => $this->description,
            'duration_label' => $this->duration_label,
        ];
    }
}
