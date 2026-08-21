<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\Tour;
use App\Models\TourDate;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Validation\Rule;

final class UpdateTourDateRequest extends StoreTourDateRequest
{
    public function authorize(): bool
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate && ($this->user()?->can('update', $tourDate) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['sometimes', 'date', 'after:now'],
            'ends_at' => ['prohibited'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500', $this->capacityRule()],
            'price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'guide_id' => ['sometimes', 'required', 'integer', $this->guideRule()],
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('routes', 'id')->where('tenant_id', $this->tenantId())],
            'provider_id' => ['sometimes', 'nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['sometimes', 'nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
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

        $startsAt = $this->parsedStartsAt() ?? $tourDate->starts_at;
        $tour = $tourDate->tour;

        if (! $tour instanceof Tour) {
            return null;
        }

        return [$startsAt, TourDate::deriveEndsAt($startsAt, $tour->duration_hours)];
    }

    /**
     * WHY: la propia salida no puede ser su propio conflicto. Sin esto, mover
     * una salida un día sería siempre un falso positivo.
     */
    protected function excludedTourDateId(): ?int
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate ? (int) $tourDate->getKey() : null;
    }

    /**
     * WHY: mover el inicio también puede crear un solape aunque el guía no
     * cambie, así que el guía a comprobar es el que quede tras el `PUT`.
     */
    protected function resolvedGuideId(): ?int
    {
        $sent = parent::resolvedGuideId();

        if ($sent !== null) {
            return $sent;
        }

        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate ? $tourDate->guide_id : null;
    }

    private function capacityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tourDate = $this->route('tourDate');

            if ($tourDate instanceof TourDate && (int) $value < $tourDate->booked_count) {
                $fail(__('La capacidad no puede ser menor que las :count reservas actuales.', ['count' => $tourDate->booked_count]));
            }
        };
    }
}
