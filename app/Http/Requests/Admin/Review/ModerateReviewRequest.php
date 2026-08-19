<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

final class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review instanceof Review && ($this->user()?->can('moderate', $review) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:approved,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
