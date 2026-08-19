<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }

    /**
     * @return array<string, string>
     */
    protected function passwordMessages(string $attribute = 'password'): array
    {
        return [
            $attribute.'.required' => __('Ingresa una contraseña.'),
            $attribute.'.string' => __('Debe ser texto.'),
            $attribute.'.min' => __('Mínimo :min caracteres.'),
            $attribute.'.confirmed' => __('Las contraseñas no coinciden.'),
            $attribute.'.password.mixed' => __('Combina mayúsculas y minúsculas.'),
            $attribute.'.password.letters' => __('Incluye al menos una letra.'),
            $attribute.'.password.numbers' => __('Incluye al menos un número.'),
            $attribute.'.password.symbols' => __('Incluye al menos un símbolo.'),
            $attribute.'.password.uncompromised' => __('Esa contraseña apareció en una filtración. Elige otra.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function currentPasswordMessages(string $attribute = 'current_password'): array
    {
        return [
            $attribute.'.required' => __('Ingresa tu contraseña actual.'),
            $attribute.'.string' => __('Debe ser texto.'),
            $attribute.'.current_password' => __('La contraseña actual no es correcta.'),
        ];
    }
}
