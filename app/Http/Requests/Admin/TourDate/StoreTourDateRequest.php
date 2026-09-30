<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Enums\Module;
use App\Http\Requests\Concerns\ValidatesTenantGuide;
use App\Models\Tour;
use App\Models\TourDate;
use App\Rules\GuideIsAvailable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreTourDateRequest extends FormRequest
{
    use ValidatesTenantGuide;

    public function authorize(): bool
    {
        return $this->user()?->can('create', TourDate::class) ?? false;
    }

    /**
     * El guía por defecto del tour es una propuesta, no una imposición
     * (regla 3 del handoff): rellena la salida nueva cuando el cliente no
     * manda ninguno, y desde ahí pasa por las mismas validaciones que
     * cualquier otro —pertenencia al tenant, rol y disponibilidad—. Si el
     * propuesto ya está ocupado esos días, la salida se rechaza igual: la
     * preferencia no salta la agenda.
     *
     * WHY (T8, revierte D7): la clave AUSENTE y la clave enviada como `null`
     * ya no son lo mismo. Ausente («no elegí nada») sigue proponiendo el
     * guía por defecto; `null` explícito («Asignar después») es la forma en
     * que el cliente pide una salida sin guía, y no se le reemplaza. Por eso
     * la comprobación es `! $this->has('guide_id')` y no `input() === null`
     * —esta última no distinguiría los dos casos—. Una cadena vacía cuenta
     * como ausente: es lo que manda un `<select>` sin tocar.
     */
    protected function prepareForValidation(): void
    {
        $tour = $this->route('tour');

        if (! $tour instanceof Tour || $tour->default_guide_id === null) {
            return;
        }

        if (! $this->has('guide_id') || $this->input('guide_id') === '') {
            $this->merge(['guide_id' => $tour->default_guide_id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'starts_at' => ['required', 'date', 'after:now'],
            // WHY (D9): el fin se deriva de `tours.duration_hours` en el
            // servidor. Aceptarlo del cliente era dejar que la regla de
            // disponibilidad se creyera un dato que nadie contrastaba.
            'ends_at' => ['prohibited'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'min_payment_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // WHY (T8, revierte D7): la salida puede crearse sin guía —
            // «Asignar después»—; `nullable` hace que Laravel se salte el
            // resto de las reglas del campo (incluida `guideRule()`) cuando
            // no llega valor, así que un `guide_id` null nunca choca contra
            // la pertenencia al tenant.
            'guide_id' => ['nullable', 'integer', $this->guideRule()],
            'route_id' => ['nullable', 'integer', $this->routeRule()],
            // T7: la fecha de cierre de reservas. Al crear, todavía tiene
            // que quedar tiempo para reservar y no puede pasarse del inicio.
            'booking_closes_at' => ['nullable', 'date', 'after:now', 'before_or_equal:starts_at'],
        ];

        return [...$rules, ...$this->logisticsRules(), ...$this->contentRules()];
    }

    /**
     * Reglas de «Contenido de la salida» (T7): cada bloque llega `null`
     * —hereda del producto— o con su propio valor, con los mismos topes que
     * el formulario del tour (`StoreTourRequest`).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function contentRules(): array
    {
        return [
            'itinerary' => ['nullable', 'array', 'max:50'],
            'itinerary.*.step_number' => ['required', 'integer', 'min:1', 'distinct'],
            'itinerary.*.title' => ['required', 'string', 'max:120'],
            'itinerary.*.description' => ['nullable', 'string', 'max:2000'],
            'itinerary.*.duration_label' => ['nullable', 'string', 'max:30'],
            'includes' => ['nullable', 'array', 'max:30'],
            'includes.*' => ['string', 'max:200'],
            'excludes' => ['nullable', 'array', 'max:30'],
            'excludes.*' => ['string', 'max:200'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['string', 'max:200'],
            'meeting_point' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * WHY (T3): con el módulo apagado `provider_id`/`hotel_ids` no tienen
     * regla, así que `validated()` los descarta y la acción nunca los toca —
     * sin esto habría que limpiarlos a mano en cada acción que guarda la salida.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function logisticsRules(): array
    {
        if (! Module::Logistics->isEnabled()) {
            return [];
        }

        return [
            'provider_id' => ['nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
        ];
    }

    /**
     * La salida solo puede operar una de las rutas del producto (spec §I). Un
     * producto sin rutas rechaza cualquier `route_id`: solo queda «Sin ruta».
     */
    protected function routeRule(): Exists
    {
        return Rule::exists('routes', 'id')->where('tour_id', $this->tourIdForRoute());
    }

    protected function tourIdForRoute(): ?int
    {
        $tour = $this->route('tour');

        return $tour instanceof Tour ? (int) $tour->getKey() : null;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateGuideAvailability($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.prohibited' => __('El fin de la salida se calcula con la duración del tour.'),
        ];
    }

    /**
     * El rango de días que la salida le va a ocupar al guía, o `null` cuando el
     * inicio todavía no es un dato válido y no hay nada que comparar.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    protected function departureRange(): ?array
    {
        $tour = $this->route('tour');
        $startsAt = $this->parsedStartsAt();

        if (! $tour instanceof Tour || $startsAt === null) {
            return null;
        }

        return [$startsAt, TourDate::deriveEndsAt($startsAt, $tour->duration_hours)];
    }

    /**
     * La salida que no cuenta como su propio conflicto: al crear, ninguna.
     */
    protected function excludedTourDateId(): ?int
    {
        return null;
    }

    protected function resolvedGuideId(): ?int
    {
        $value = $this->input('guide_id');

        return is_numeric($value) ? (int) $value : null;
    }

    protected function parsedStartsAt(): ?CarbonInterface
    {
        $value = $this->input('starts_at');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function validateGuideAvailability(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['starts_at', 'guide_id'])) {
            return;
        }

        $range = $this->departureRange();
        $guideId = $this->resolvedGuideId();

        if ($range === null || $guideId === null) {
            return;
        }

        (new GuideIsAvailable($range[0], $range[1], $this->excludedTourDateId()))->validate(
            'guide_id',
            $guideId,
            fn (string $message) => $validator->errors()->add('guide_id', $message),
        );
    }
}
