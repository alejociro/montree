<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.invite') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'in:'.implode(',', [UserRole::Admin->value, UserRole::Sales->value, UserRole::Operator->value, UserRole::Guide->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa el correo de la persona a invitar.',
            'email.email' => 'Correo inválido.',
            'email.max' => 'Máximo :max caracteres.',
            'name.string' => 'Debe ser texto.',
            'name.max' => 'Máximo :max caracteres.',
            'role.required' => 'Elige un rol.',
            'role.in' => 'Ese rol no es válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'name' => 'nombre',
            'role' => 'rol',
        ];
    }
}
