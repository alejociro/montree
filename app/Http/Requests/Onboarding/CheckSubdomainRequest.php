<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

final class CheckSubdomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * WHY: format is not enforced here — an invalid slug still returns a 200 with
     * `reason: "invalid_format"` (see contracts.md), so the endpoint accepts any
     * string and the action classifies it.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
        ];
    }

    public function slug(): string
    {
        return (string) $this->validated('slug');
    }
}
