<?php

declare(strict_types=1);

namespace App\Http\Resources\Passenger;

use App\Models\BookingTraveler;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de la planilla. Es el mismo objeto en las dos zonas: el guía recibe
 * exactamente lo mismo que el administrador.
 *
 * WHY: aquí está el único `mergeWhen()` del dato de salud (D7). Quien no tiene
 * `bookings.passengers.medical.view` no recibe los cuatro campos: no llegan al
 * navegador ocultos por CSS. Dos comprobaciones distintas para el mismo dato es
 * la forma habitual de que una se olvide.
 *
 * @mixin BookingTraveler
 */
final class PassengerResource extends JsonResource
{
    private const MEDICAL_ACCESS = 'passenger.can_view_medical';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource->booking;

        return [
            'id' => $this->id,
            'booking_number' => $booking?->booking_number,
            'tour_date_id' => $booking?->tour_date_id,
            'departure_starts_at' => $booking?->tourDate?->starts_at?->toIso8601String(),
            'full_name' => $this->full_name,
            'is_minor' => $this->is_minor === null ? null : (bool) $this->is_minor,
            'document_type' => $this->document_type?->value,
            'document_type_label' => $this->document_type?->label(),
            'document_number' => $this->document_number,
            'email' => $this->email,
            'phone' => $this->phone,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_relationship' => $this->emergency_contact_relationship,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            $this->mergeWhen(self::canViewMedical($request), fn (): array => [
                'eps' => $this->eps?->value,
                'eps_label' => $this->eps_label,
                'eps_other' => $this->eps_other,
                'medical_notes' => $this->medical_notes,
            ]),
            'dietary_restrictions' => $this->dietary_restrictions,
            'payment' => $booking?->passengerShare(),
        ];
    }

    /**
     * WHY: memoizado en el propio request. Con teams activos, cada `can()`
     * vuelve a leer los roles del usuario, y una planilla de 50 filas pagaba
     * 50 consultas por preguntar dos veces por fila lo mismo.
     */
    public static function canViewMedical(Request $request): bool
    {
        if (! $request->attributes->has(self::MEDICAL_ACCESS)) {
            $request->attributes->set(
                self::MEDICAL_ACCESS,
                $request->user()?->can('viewMedical', BookingTraveler::class) ?? false,
            );
        }

        return (bool) $request->attributes->get(self::MEDICAL_ACCESS);
    }
}
