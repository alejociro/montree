<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tour;

use App\Enums\TourDifficulty;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTourRequest extends FormRequest
{
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
        ];
    }
}
