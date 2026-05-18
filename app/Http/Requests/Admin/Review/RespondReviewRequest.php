<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Review;

use Illuminate\Foundation\Http\FormRequest;

final class RespondReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['admin', 'operator']) ?? false;
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
