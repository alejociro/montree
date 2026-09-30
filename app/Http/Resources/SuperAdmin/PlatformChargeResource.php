<?php

declare(strict_types=1);

namespace App\Http\Resources\SuperAdmin;

use App\Models\PlatformCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformCharge
 */
class PlatformChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'charged_at' => $this->charged_at->toIso8601String(),
            'booking' => $this->whenLoaded('booking', fn (): array => [
                'id' => $this->resource->booking->id,
                'booking_number' => $this->resource->booking->booking_number,
                'total_amount' => (string) $this->resource->booking->total_amount,
                'currency' => $this->resource->booking->currency,
            ]),
            'base_amount' => (string) $this->base_amount,
            'applied_rate' => (string) $this->applied_rate,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'tier_from' => $this->tier_from === null ? null : (string) $this->tier_from,
            'tier_to' => $this->tier_to === null ? null : (string) $this->tier_to,
            'max_charge' => $this->max_charge === null ? null : (string) $this->max_charge,
            'was_capped' => (bool) $this->was_capped,
            'schedule_scope' => $this->schedule_scope,
        ];
    }
}
