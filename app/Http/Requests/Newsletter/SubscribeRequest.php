<?php

declare(strict_types=1);

namespace App\Http\Requests\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

final class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
