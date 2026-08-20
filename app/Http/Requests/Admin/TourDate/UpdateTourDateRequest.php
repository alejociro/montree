<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\TourDate;
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
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500', $this->capacityRule()],
            'price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'guide_id' => ['sometimes', 'nullable', 'integer', $this->guideRule()],
            'route_id' => ['sometimes', 'nullable', 'integer', Rule::exists('routes', 'id')->where('tenant_id', $this->tenantId())],
            'provider_id' => ['sometimes', 'nullable', 'integer', Rule::exists('providers', 'id')->where('tenant_id', $this->tenantId())],
            'hotel_ids' => ['sometimes', 'nullable', 'array'],
            'hotel_ids.*' => ['integer', 'distinct', Rule::exists('hotels', 'id')->where('tenant_id', $this->tenantId())],
        ];
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
