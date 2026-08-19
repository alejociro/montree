<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return [
            'name.required' => __('Ingresa tu nombre.'),
            'name.string' => __('Debe ser texto.'),
            'name.max' => __('Máximo :max caracteres.'),
            'email.required' => __('Ingresa tu correo.'),
            'email.string' => __('Debe ser texto.'),
            'email.email' => __('Correo inválido.'),
            'email.max' => __('Máximo :max caracteres.'),
            'email.unique' => __('Ese correo ya está en uso.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function profileAttributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => __('correo electrónico'),
        ];
    }
}
