<?php

declare(strict_types=1);

namespace App\Http\Resources\Booking;

use App\Models\BookingTraveler;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha del viajero dentro de su propia reserva.
 *
 * WHY: el bloque de salud y emergencia solo se serializa para el dueño de la
 * reserva. La planilla de la agencia y la del guía no pasan por aquí: usan
 * `PassengerResource`, con su propia máscara por permiso médico.
 *
 * @mixin BookingTraveler
 */
final class BookingTravelerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'is_minor' => $this->is_minor,
            'email' => $this->email,
            'phone' => $this->phone,
            'document_type' => $this->document_type?->value,
            'document_number' => $this->document_number,
            'birth_date' => $this->birth_date?->toDateString(),
            $this->mergeWhen($this->belongsToRequestUser($request), fn (): array => [
                'emergency_contact_name' => $this->emergency_contact_name,
                'emergency_contact_relationship' => $this->emergency_contact_relationship,
                'emergency_contact_phone' => $this->emergency_contact_phone,
                'eps' => $this->eps?->value,
                'eps_label' => $this->eps_label,
                'eps_other' => $this->eps_other,
                'medical_notes' => $this->medical_notes,
                'dietary_restrictions' => $this->dietary_restrictions,
            ]),
        ];
    }

    private function belongsToRequestUser(Request $request): bool
    {
        $userId = $request->user()?->getKey();

        return $userId !== null && $userId === $this->resource->booking?->user_id;
    }
}
