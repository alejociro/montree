<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Models\Tenant;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.role.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            // WHY: la lista no es fija — incluye los roles propios de la agencia, que se
            // crean en runtime desde `/admin/roles` (F018 fase 3B).
            'roles.*' => ['string', Rule::in($this->assignableRoles())],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validatedRoles(): array
    {
        /** @var array<int, string> $roles */
        $roles = $this->validated('roles');

        return array_values(array_unique($roles));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Elige al menos un rol.',
            'roles.array' => 'Envía una lista de roles.',
            'roles.min' => 'Elige al menos un rol.',
            'roles.*.in' => 'Ese rol no existe en tu agencia.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['roles' => 'roles'];
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
}
