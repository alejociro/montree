<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Review;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

final class RespondReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review instanceof Review && ($this->user()?->can('respond', $review) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'max:1000'],
        ];
    }
}
