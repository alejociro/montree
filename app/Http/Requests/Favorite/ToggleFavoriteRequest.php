<?php

declare(strict_types=1);

namespace App\Http\Requests\Favorite;

use App\Enums\TourStatus;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Tenant::current() !== null && $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tour_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $tour = Tour::query()->where('id', $value)->where('status', TourStatus::Active)->first();
                    if ($tour === null) {
                        $fail('The selected tour is not available.');
                    }
                },
            ],
        ];
    }
}
