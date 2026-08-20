<?php

declare(strict_types=1);

namespace App\Http\Resources\Passenger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pie de la planilla, calculado sobre el resultado filtrado.
 *
 * WHY: `with_notes` se omite sin el permiso médico. El conteo de pasajeros con
 * observaciones también es dato clínico agregado: dice cuántos hay aunque no
 * diga quiénes.
 */
final class PassengerManifestSummary extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = (array) $this->resource;

        return [
            'total_passengers' => $summary['total_passengers'],
            'with_due' => $summary['with_due'],
            'paid' => $summary['paid'],
            $this->mergeWhen(PassengerResource::canViewMedical($request), fn (): array => [
                'with_notes' => $summary['with_notes'],
            ]),
            'total_due_amount' => $summary['total_due_amount'],
            'currency' => $summary['currency'],
        ];
    }
}
