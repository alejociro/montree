<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TeamIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('team.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(TenantMembershipStatus::class)],
            'role' => $this->roleRules(),
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function status(): ?TenantMembershipStatus
    {
        $status = (string) ($this->validated('status') ?? '');

        return $status === '' ? null : TenantMembershipStatus::from($status);
    }

    public function role(): ?string
    {
        $role = trim((string) ($this->validated('role') ?? ''));

        return $role === '' ? null : $role;
    }

    public function search(): ?string
    {
        $search = trim((string) ($this->validated('search') ?? ''));

        return $search === '' ? null : $search;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Ese rol no existe en tu agencia.',
        ];
    }

    /**
     * WHY: la lista no es fija — incluye los roles propios de la agencia, creados en
     * runtime desde `/admin/roles` (F018 fase 3B) —, y `Rule::in` la necesita
     * materializada. Se consulta solo cuando el filtro vino, para no sumarle una query
     * al listado sin filtrar, que es el 90% de las visitas.
     *
     * @return array<int, mixed>
     */
    private function roleRules(): array
    {
        $rules = ['sometimes', 'nullable', 'string'];

        if (! $this->has('role')) {
            return $rules;
        }

        return [...$rules, Rule::in($this->assignableRoles())];
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
