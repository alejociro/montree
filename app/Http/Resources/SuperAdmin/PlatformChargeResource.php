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
            'type' => $this->commission_type->value,
            'applied_value' => (string) $this->applied_value,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
        ];
    }
}
