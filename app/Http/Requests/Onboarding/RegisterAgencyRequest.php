<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Rules\NotReservedSubdomain;
use Illuminate\Foundation\Http\FormRequest;
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

    /**
     * @return array<string, mixed>
     */
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
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('No pudimos crear la cuenta con esos datos.'),
            'subdomain.unique' => __('Ese subdominio ya fue reclamado.'),
        ];
    }

    /**
     * @return array{agency_name: string, subdomain: string, founder_name: string, email: string, password: string}
     */
    public function agencyData(): array
    {
        /** @var array{agency_name: string, subdomain: string, founder_name: string, email: string, password: string} $data */
        $data = $this->only(['agency_name', 'subdomain', 'founder_name', 'email', 'password']);

        return $data;
    }
}
