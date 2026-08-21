<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Guide;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class GuideAvailabilityRequest extends FormRequest
{
    /** Un rango más largo que esto no es un select: es un reporte. */
    private const MAX_DAYS = 180;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'exclude_tour_date_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                if ($this->from()->diffInDays($this->to()) > self::MAX_DAYS) {
                    $validator->errors()->add('to', __('El rango no puede superar :days días.', ['days' => self::MAX_DAYS]));
                }
            },
        ];
    }

    public function from(): Carbon
    {
        return Carbon::parse((string) $this->validated('from'))->startOfDay();
    }

    public function to(): Carbon
    {
        return Carbon::parse((string) $this->validated('to'))->startOfDay();
    }

    public function excludeTourDateId(): ?int
    {
        $value = $this->validated('exclude_tour_date_id');

        return $value === null ? null : (int) $value;
    }
}
