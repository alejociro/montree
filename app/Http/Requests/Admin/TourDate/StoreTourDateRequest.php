<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Http\Requests\Concerns\ValidatesTenantGuide;
use App\Models\Tour;
use App\Models\TourDate;
use App\Rules\GuideIsAvailable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

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
     */
    protected function prepareForValidation(): void
    {
        $tour = $this->route('tour');
        $sent = $this->input('guide_id');

        if ($tour instanceof Tour && ($sent === null || $sent === '') && $tour->default_guide_id !== null) {
            $this->merge(['guide_id' => $tour->default_guide_id]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
            // WHY (D9): el fin se deriva de `tours.duration_hours` en el
            // servidor. Aceptarlo del cliente era dejar que la regla de
            // disponibilidad se creyera un dato que nadie contrastaba.
            'ends_at' => ['prohibited'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // WHY (D7): toda salida lleva guía. No existe «Sin asignar».
            'guide_id' => ['required', 'integer', $this->guideRule()],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')->where('tenant_id', $this->tenantId())],
            'provider_id' => ['nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
        ];
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
