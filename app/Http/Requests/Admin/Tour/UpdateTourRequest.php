<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use App\Http\Requests\Concerns\ValidatesTenantGuide;
use App\Models\Category;
use App\Models\Tour;
use App\Queries\GuideAvailabilityQuery;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourRequest extends FormRequest
{
    use ValidatesTenantGuide;

    private const SUPPORTED_CURRENCIES = ['USD', 'COP', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'];

    public function authorize(): bool
    {
        $tour = $this->route('tour');

        return $tour instanceof Tour && ($this->user()?->can('update', $tour) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'description' => ['sometimes', 'required', 'string', 'max:10000'],
            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists((new Category)->getTable(), 'id'),
            ],
            'base_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', Rule::in(self::SUPPORTED_CURRENCIES)],
            'duration_hours' => ['sometimes', 'required', 'integer', 'min:1', 'max:240'],
            'default_guide_id' => ['sometimes', 'nullable', 'integer', $this->guideRule()],
            'difficulty' => ['sometimes', 'required', 'string', Rule::in(array_column(TourDifficulty::cases(), 'value'))],
            'default_capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'meeting_point' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meeting_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'meeting_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'includes' => ['sometimes', 'nullable', 'array', 'max:30'],
            'includes.*' => ['string', 'max:200'],
            'excludes' => ['sometimes', 'nullable', 'array', 'max:30'],
            'excludes.*' => ['string', 'max:200'],
            'requirements' => ['sometimes', 'nullable', 'array', 'max:30'],
            'requirements.*' => ['string', 'max:200'],
            'itinerary' => ['sometimes', 'nullable', 'array', 'max:50'],
            'itinerary.*.step_number' => ['required_with:itinerary', 'integer', 'min:1', 'distinct'],
            'itinerary.*.title' => ['required_with:itinerary', 'string', 'max:120'],
            'itinerary.*.description' => ['nullable', 'string', 'max:2000'],
            'itinerary.*.duration_label' => ['nullable', 'string', 'max:30'],
            'stops' => ['sometimes', 'nullable', 'array', 'max:40'],
            'stops.*.kind' => ['required_with:stops', 'string', Rule::in(array_column(TourStopKind::cases(), 'value'))],
            'stops.*.name' => ['required_with:stops', 'string', 'max:120'],
            'stops.*.label' => ['nullable', 'string', 'max:40'],
            'stops.*.place' => ['nullable', 'string', 'max:120'],
            'stops.*.time' => ['nullable', 'string', 'max:30'],
            'stops.*.latitude' => ['required_with:stops', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['required_with:stops', 'numeric', 'between:-180,180'],
            'stops.*.itinerary_step' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateDurationChange($validator),
            function (Validator $validator): void {
                $kinds = array_column((array) $this->input('stops', []), 'kind');

                foreach ([TourStopKind::Pickup, TourStopKind::Drop] as $unique) {
                    if (count(array_keys($kinds, $unique->value, true)) > 1) {
                        $validator->errors()->add(
                            'stops',
                            __('Solo puede haber una parada de tipo :kind.', ['kind' => $unique->label()]),
                        );
                    }
                }
            },
        ];
    }

    /**
     * Cambiar `duration_hours` alarga retroactivamente el `ends_at` derivado de
     * todas las salidas futuras del tour, y eso puede cruzar dos que hoy no se
     * tocan (D9). Se avisa **antes** de guardar, con las salidas nombradas: el
     * administrador decide si mueve una salida o deja la duración como estaba.
     */
    private function validateDurationChange(Validator $validator): void
    {
        $tour = $this->route('tour');
        $duration = $this->input('duration_hours');

        if (! $tour instanceof Tour || ! is_numeric($duration) || $validator->errors()->has('duration_hours')) {
            return;
        }

        if ((int) $duration === $tour->duration_hours) {
            return;
        }

        $conflicts = app(GuideAvailabilityQuery::class)->durationChangeConflicts($tour, (int) $duration);

        if ($conflicts === []) {
            return;
        }

        $validator->errors()->add('duration_hours', __('Con esa duración quedarían salidas en solape: :conflicts', [
            'conflicts' => implode(' · ', $conflicts),
        ]));
    }
}
