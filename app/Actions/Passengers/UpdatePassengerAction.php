<?php

declare(strict_types=1);

namespace App\Actions\Passengers;

use App\Enums\Eps;
use App\Models\BookingTraveler;

/**
 * Escritura de un pasajero: la comparten el flujo del viajero (sync de su
 * reserva) y el panel de la agencia.
 *
 * WHY: `eps_other` solo tiene sentido cuando la EPS es «Otra». La regla vive
 * aquí y en ningún otro sitio; duplicarla es cómo una de las dos entradas
 * termina guardando un texto libre que la pantalla ya no muestra.
 */
final class UpdatePassengerAction
{
    private const FIELDS = [
        'full_name',
        'is_minor',
        'document_type',
        'document_number',
        'birth_date',
        'nationality',
        'email',
        'phone',
        'dietary_restrictions',
        'medical_notes',
        'eps',
        'eps_other',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(BookingTraveler $passenger, array $attributes): BookingTraveler
    {
        $passenger->fill($this->normalize($attributes))->save();

        return $passenger;
    }

    /**
     * El campo ausente se escribe como `null`: el pasajero se reemplaza
     * entero, no se parchea campo a campo.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $normalized[$field] = $attributes[$field] ?? null;
        }

        $normalized['is_minor'] = (bool) ($attributes['is_minor'] ?? false);
        $normalized['eps_other'] = $this->epsDetail($normalized['eps'], $normalized['eps_other']);

        return $normalized;
    }

    private function epsDetail(mixed $eps, mixed $detail): ?string
    {
        $case = $eps instanceof Eps ? $eps : Eps::tryFrom((string) ($eps ?? ''));

        if ($case === null || ! $case->requiresDetail()) {
            return null;
        }

        $detail = trim((string) ($detail ?? ''));

        return $detail === '' ? null : $detail;
    }
}
