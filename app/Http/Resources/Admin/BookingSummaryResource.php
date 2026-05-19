<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = is_array($this->contact_snapshot) ? $this->contact_snapshot : [];

        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'status' => $this->status->value,
            'customer_name' => $contact['name'] ?? $this->whenLoaded('user', fn () => $this->user?->name),
            'customer_email' => $contact['email'] ?? $this->whenLoaded('user', fn () => $this->user?->email),
            'tour_name' => $this->whenLoaded('tour', fn () => $this->tour?->name),
            'tour_date_starts_at' => $this->whenLoaded('tourDate', fn () => $this->tourDate?->starts_at?->toIso8601String()),
            'travelers_count' => $this->travelers_count,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
