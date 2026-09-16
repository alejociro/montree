<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tour = $this->route('tour');

        return $tour instanceof Tour && ($this->user()?->can('update', $tour) ?? false);
    }

    /**
     * Solo el nombre es obligatorio: la ficha se llena a lo largo de la
     * temporada y exigir el resto dejaría sin editar a las rutas ya cargadas.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::enum(RouteKind::class)],
            'difficulty' => ['nullable', Rule::enum(TourDifficulty::class)],
            'start_point' => ['nullable', 'string', 'max:500'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'end_point' => ['nullable', 'string', 'max:500'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'max_altitude_m' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'elevation_gain_m' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'group_capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'seasons' => ['nullable', 'array', 'max:4'],
            'seasons.*' => [Rule::enum(RouteSeason::class)],
            'safety_notes' => ['nullable', 'string', 'max:2000'],
            'required_gear' => ['nullable', 'array', 'max:20'],
            'required_gear.*' => ['string', 'max:80'],
            'permits' => ['nullable', 'string', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'stops' => ['nullable', 'array', 'max:40'],
            'stops.*.name' => ['required', 'string', 'max:160'],
            'stops.*.kind' => ['required', Rule::enum(TourStopKind::class)],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'stops.*.time_label' => ['nullable', 'string', 'max:30'],
        ];
    }
}
