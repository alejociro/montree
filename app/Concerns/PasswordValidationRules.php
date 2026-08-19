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
            $attribute.'.required' => 'Ingresa una contraseña.',
            $attribute.'.string' => 'Debe ser texto.',
            $attribute.'.min' => 'Mínimo :min caracteres.',
            $attribute.'.confirmed' => 'Las contraseñas no coinciden.',
            $attribute.'.password.mixed' => 'Combina mayúsculas y minúsculas.',
            $attribute.'.password.letters' => 'Incluye al menos una letra.',
            $attribute.'.password.numbers' => 'Incluye al menos un número.',
            $attribute.'.password.symbols' => 'Incluye al menos un símbolo.',
            $attribute.'.password.uncompromised' => 'Esa contraseña apareció en una filtración. Elige otra.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function currentPasswordMessages(string $attribute = 'current_password'): array
    {
        return [
            $attribute.'.required' => 'Ingresa tu contraseña actual.',
            $attribute.'.string' => 'Debe ser texto.',
            $attribute.'.current_password' => 'La contraseña actual no es correcta.',
        ];
    }
}
