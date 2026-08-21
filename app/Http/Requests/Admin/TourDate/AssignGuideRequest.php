<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\Tour;
use App\Models\TourDate;
use Carbon\CarbonInterface;

/**
 * El tercer camino que asigna guía. Antes validaba `nullable` + `exists:users`
 * y nada más: cualquier usuario del mundo podía quedar de guía de una salida, y
 * la disponibilidad se saltaba por acá (D7, D9).
 */
final class AssignGuideRequest extends StoreTourDateRequest
{
    public function authorize(): bool
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate && ($this->user()?->can('assignGuide', $tourDate) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'guide_id' => ['required', 'integer', $this->guideRule()],
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    protected function departureRange(): ?array
    {
        $tourDate = $this->route('tourDate');

        if (! $tourDate instanceof TourDate) {
            return null;
        }

        $tour = $tourDate->tour;

        if (! $tour instanceof Tour) {
            return null;
        }

        return [$tourDate->starts_at, TourDate::deriveEndsAt($tourDate->starts_at, $tour->duration_hours)];
    }

    protected function excludedTourDateId(): ?int
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate ? (int) $tourDate->getKey() : null;
    }
}
