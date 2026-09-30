<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\Module;
use App\Models\Tour;
use App\Models\TourDate;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

final class UpdateTourDateRequest extends StoreTourDateRequest
{
    public function authorize(): bool
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate && ($this->user()?->can('update', $tourDate) ?? false);
    }

    /**
     * Editar no propone nada: la salida ya tiene guía y cambiarlo es un acto
     * explícito. La propuesta del guía por defecto es solo para la salida nueva.
     */
    protected function prepareForValidation(): void {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'starts_at' => ['sometimes', 'date', 'after:now'],
            'ends_at' => ['prohibited'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500', $this->capacityRule()],
            'price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'min_payment_pct' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            // T8 (revierte D7): editar puede quitarle el guía a la salida —
            // manda `guide_id: null`— sin que ninguna regla de negocio lo
            // impida hoy; solo tiene que seguir siendo un miembro válido del
            // tenant cuando sí se manda alguno.
            'guide_id' => ['sometimes', 'nullable', 'integer', $this->guideRule()],
            'route_id' => ['sometimes', 'nullable', 'integer', $this->routeRule()],
            // T7: al editar no se exige que siga en el futuro —una salida
            // puede llegar con el cierre ya pasado y el operador solo quiere
            // ajustar otra cosa— pero sigue sin poder pasarse del inicio.
            'booking_closes_at' => ['sometimes', 'nullable', 'date', $this->bookingClosesAtRule()],
        ];

        return [...$rules, ...$this->logisticsRules(), ...$this->contentRules()];
    }

    /**
     * WHY: `before_or_equal:starts_at` compararía contra el `starts_at`
     * enviado, y en un `PUT` parcial ese campo puede no venir. El límite real
     * es el inicio que quede tras el guardado: el enviado si llega, si no el
     * que ya tiene la salida.
     */
    private function bookingClosesAtRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $startsAt = $this->parsedStartsAt() ?? $this->route('tourDate')?->starts_at;

            if ($startsAt === null) {
                return;
            }

            try {
                $closesAt = Carbon::parse((string) $value);
            } catch (\Throwable) {
                return;
            }

            if ($closesAt->gt($startsAt)) {
                $fail(__('La fecha de cierre de reservas no puede ser posterior al inicio de la salida.'));
            }
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function logisticsRules(): array
    {
        if (! Module::Logistics->isEnabled()) {
            return [];
        }

        return [
            'provider_id' => ['sometimes', 'nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['sometimes', 'nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
        ];
    }

    protected function tourIdForRoute(): ?int
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate ? $tourDate->tour_id : null;
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
     *
     * T8: la clave AUSENTE («no toqué el guía») y enviada como `null`
     * explícito («lo quito») ya no son lo mismo —igual que en
     * `StoreTourDateRequest::prepareForValidation()`—. Ausente conserva el
     * guía que ya tenía la salida para poder revalidar su disponibilidad si
     * cambió el inicio; `null` explícito deja la salida sin guía y no hay
     * nada que revalidar.
     */
    protected function resolvedGuideId(): ?int
    {
        if ($this->has('guide_id')) {
            return parent::resolvedGuideId();
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
