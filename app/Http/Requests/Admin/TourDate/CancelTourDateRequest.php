<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;

final class CancelTourDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tour::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
