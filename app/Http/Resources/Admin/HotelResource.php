<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hotel
 */
final class HotelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'accommodation_type' => $this->accommodation_type?->value,
            'star_rating' => $this->star_rating,
            'description' => $this->description,
            'legal_name' => $this->legal_name,
            'tax_id' => $this->tax_id,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'directions' => $this->directions,
            'total_capacity' => $this->total_capacity,
            'currency' => $this->currency,
            'rates_valid_until' => $this->rates_valid_until?->toDateString(),
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'amenities' => $this->amenities ?? [],
            'meal_plan' => $this->meal_plan?->value,
            'diets' => $this->diets,
            'restrictions' => $this->restrictions,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'emergency_contact' => $this->emergency_contact,
            'cancellation_policy' => $this->cancellation_policy?->value,
            'payment_terms' => $this->payment_terms?->value,
            'notes' => $this->notes,
            'rooms' => HotelRoomResource::collection($this->whenLoaded('rooms', fn () => $this->rooms, collect()))->resolve(),
            'tour_dates_count' => (int) ($this->tour_dates_count ?? 0),
        ];
    }
}
