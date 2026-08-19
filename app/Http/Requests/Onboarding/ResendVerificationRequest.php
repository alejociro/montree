<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\LowercasesInput;
use Illuminate\Foundation\Http\FormRequest;

final class ResendVerificationRequest extends FormRequest
{
    use LowercasesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->lowercaseInput('email');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Ingresa tu correo.'),
            'email.string' => __('Debe ser texto.'),
            'email.email' => __('Correo inválido.'),
            'email.max' => __('Máximo :max caracteres.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('correo electrónico')];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
