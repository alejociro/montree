<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La reserva que agrupa una transacción, como se ve en el listado.
 *
 * @mixin Booking
 */
class TransactionBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'booking_number' => $this->booking_number,
            'holder_name' => $this->contactField('name') ?? $this->user?->name,
            'tour_name' => $this->tour?->name,
            'tour_date_id' => $this->tour_date_id,
            'starts_at' => $this->tourDate?->starts_at?->toIso8601String(),
        ];
    }

    protected function contactField(string $key): ?string
    {
        $contact = is_array($this->contact_snapshot) ? $this->contact_snapshot : [];

        return isset($contact[$key]) ? (string) $contact[$key] : null;
    }
}
