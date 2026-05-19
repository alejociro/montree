<?php

declare(strict_types=1);

namespace App\Http\Resources\Booking;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
final class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'booking_number' => $this->booking_number,
            'status' => $this->status->value,
            'travelers_count' => $this->travelers_count,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'currency' => $this->currency,
            'special_requests' => $this->special_requests,
            'contact_snapshot' => $this->contact_snapshot,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'tour' => $this->whenLoaded('tour', fn () => [
                'id' => $this->tour->id,
                'slug' => $this->tour->slug,
                'name' => $this->tour->name,
            ]),
            'tour_date' => $this->whenLoaded('tourDate', fn () => [
                'id' => $this->tourDate->id,
                'starts_at' => $this->tourDate->starts_at->toIso8601String(),
                'ends_at' => $this->tourDate->ends_at?->toIso8601String(),
            ]),
            'promotion' => $this->whenLoaded('promotion', fn () => $this->promotion === null ? null : [
                'id' => $this->promotion->id,
                'code' => $this->promotion->code,
            ]),
            'travelers' => $this->whenLoaded('travelers', fn () => $this->travelers->map(fn ($t) => [
                'id' => $t->id,
                'full_name' => $t->full_name,
                'email' => $t->email,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
