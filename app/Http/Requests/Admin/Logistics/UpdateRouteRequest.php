<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

final class UpdateRouteRequest extends LogisticsFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'distance_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'duration_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
