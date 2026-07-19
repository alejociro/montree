<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

final class StoreRouteRequest extends LogisticsFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'duration_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
