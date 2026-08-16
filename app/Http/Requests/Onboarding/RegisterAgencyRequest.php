<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Rules\NotReservedSubdomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class RegisterAgencyRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subdomain' => is_string($this->input('subdomain')) ? mb_strtolower($this->input('subdomain')) : $this->input('subdomain'),
            'email' => is_string($this->input('email')) ? mb_strtolower($this->input('email')) : $this->input('email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'agency_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required', 'string', 'max:63',
                'regex:/^[a-z0-9][a-z0-9-]{1,62}$/',
                Rule::unique('tenants', 'slug'),
                new NotReservedSubdomain,
            ],
            'founder_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRulesWithoutConfirmation(),
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    private function passwordRulesWithoutConfirmation(): array
    {
        return array_values(array_filter(
            $this->passwordRules(),
            static fn (mixed $rule): bool => $rule !== 'confirmed',
        ));
    }

    public function messages(): array
    {
        return [
            'string' => 'Debe ser texto.',
            'max' => 'Máximo :max caracteres.',

            'agency_name.required' => 'Ingresa el nombre de tu agencia.',

            'subdomain.required' => 'Elige un subdominio.',
            'subdomain.regex' => 'Solo minúsculas, números y guiones, sin empezar por guion.',
            'subdomain.unique' => 'Ese subdominio ya fue reclamado.',

            'founder_name.required' => 'Ingresa tu nombre.',

            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Correo inválido.',
            'email.unique' => 'No pudimos crear la cuenta con esos datos.',

            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'Mínimo :min caracteres.',
            'password.password.mixed' => 'Combina mayúsculas y minúsculas.',
            'password.password.letters' => 'Incluye al menos una letra.',
            'password.password.numbers' => 'Incluye al menos un número.',
            'password.password.symbols' => 'Incluye al menos un símbolo.',
            'password.password.uncompromised' => 'Esa contraseña apareció en una filtración. Elige otra.',

            'password_confirmation.required' => 'Confirma tu contraseña.',
            'password_confirmation.same' => 'Las contraseñas no coinciden.',
        ];
    }

    public function attributes(): array
    {
        return [
            'agency_name' => 'nombre de la agencia',
            'subdomain' => 'subdominio',
            'founder_name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ];
    }

    public function agencyData(): array
    {
        return Arr::except($this->validated(), 'password_confirmation');
    }
}
