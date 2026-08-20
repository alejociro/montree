<?php

declare(strict_types=1);

namespace App\Http\Requests\Passenger\Concerns;

use App\Enums\DocumentType;
use App\Enums\Eps;
use App\Models\BookingTraveler;
use Illuminate\Validation\Rule;

/**
 * Cuerpo del pasajero en el panel: alta y edición comparten reglas y máscara.
 */
trait PassengerPayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function passengerRules(bool $isMinorRequired): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'is_minor' => [$isMinorRequired ? 'required' : 'nullable', 'boolean'],
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'document_number' => ['nullable', 'string', 'max:40', 'required_with:document_type'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40', 'required_with:emergency_contact_name'],
            'eps' => ['nullable', Rule::enum(Eps::class)],
            'eps_other' => ['nullable', 'string', 'max:120', 'required_if:eps,other'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Máscara de escritura del dato de salud (D7).
     *
     * WHY: descarta, no rechaza. `sales` tiene `bookings.update` y un caso
     * legítimo —corregir un teléfono—; devolverle 403 se lo rompería. Lo que no
     * puede es escribir a ciegas lo que no puede leer, así que sus tres campos
     * sensibles se reemplazan por lo que ya está guardado: en un alta, `null`;
     * en una edición, el valor previo, que así sobrevive intacto.
     */
    protected function maskMedicalInput(?BookingTraveler $passenger): void
    {
        if ($this->user()?->can('viewMedical', BookingTraveler::class)) {
            return;
        }

        $this->merge([
            'eps' => $passenger?->eps?->value,
            'eps_other' => $passenger?->eps_other,
            'medical_notes' => $passenger?->medical_notes,
        ]);
    }
}
