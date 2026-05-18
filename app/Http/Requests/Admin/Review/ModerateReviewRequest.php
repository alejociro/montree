<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Review;

use Illuminate\Foundation\Http\FormRequest;

final class ModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
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
