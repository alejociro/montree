<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\TourDate;

use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;

final class CancelTourDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tourDate = $this->route('tourDate');

        return $tourDate instanceof TourDate && ($this->user()?->can('cancel', $tourDate) ?? false);
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
