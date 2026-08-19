<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Models\Tenant;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // WHY: incluye los roles propios de la agencia (F018 fase 3B), que no son una
            // lista fija — se crean en runtime desde `/admin/roles`.
            'role' => ['required', Rule::in($this->assignableRoles())],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function assignableRoles(): array
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return [];
        }

        return app(TenantRoleCatalog::class)->assignableNames($tenant);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Ingresa el correo de la persona a invitar.'),
            'email.email' => __('Correo inválido.'),
            'email.max' => __('Máximo :max caracteres.'),
            'name.string' => __('Debe ser texto.'),
            'name.max' => __('Máximo :max caracteres.'),
            'role.required' => __('Elige un rol.'),
            'role.in' => __('Ese rol no es válido.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('correo electrónico'),
            'name' => 'nombre',
            'role' => 'rol',
        ];
    }
}
