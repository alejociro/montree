<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\LowercasesInput;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Rules\NotReservedSubdomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

final class RegisterAgencyRequest extends FormRequest
{
    use LowercasesInput;
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->lowercaseInput('subdomain', 'email');
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
            'string' => __('Debe ser texto.'),
            'max' => __('Máximo :max caracteres.'),

            'agency_name.required' => __('Ingresa el nombre de tu agencia.'),

            'subdomain.required' => __('Elige un subdominio.'),
            'subdomain.regex' => __('Solo minúsculas, números y guiones, sin empezar por guion.'),
            'subdomain.unique' => __('Ese subdominio ya fue reclamado.'),

            'founder_name.required' => __('Ingresa tu nombre.'),

            'email.required' => __('Ingresa tu correo.'),
            'email.email' => __('Correo inválido.'),
            'email.unique' => __('No pudimos crear la cuenta con esos datos.'),

            'password_confirmation.required' => __('Confirma tu contraseña.'),
            'password_confirmation.same' => __('Las contraseñas no coinciden.'),

            ...$this->passwordMessages(),
        ];
    }

    public function attributes(): array
    {
        return [
            'agency_name' => __('nombre de la agencia'),
            'subdomain' => __('subdominio'),
            'founder_name' => __('nombre'),
            'email' => __('correo electrónico'),
            'password' => __('contraseña'),
            'password_confirmation' => __('confirmación de contraseña'),
        ];
    }

    public function agencyData(): array
    {
        return Arr::except($this->validated(), 'password_confirmation');
    }
}
