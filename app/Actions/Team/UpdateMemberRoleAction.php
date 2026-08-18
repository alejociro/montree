<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\UserRole;
use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;

final class UpdateMemberRoleAction
{
    /**
     * Reemplaza el juego completo de roles del miembro dentro de la agencia.
     *
     * @param  array<int, string>  $roles  nombres de rol ya validados contra el catálogo del tenant
     */
    public function handle(Tenant $tenant, User $user, array $roles): User
    {
        if (! $user->belongsToTenant($tenant)) {
            throw CrossTenantAccessException::forMember();
        }

        if ($roles === []) {
            throw TeamException::rolesRequired();
        }

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $currentRoles = $user->getRoleNames()->all();

        $losesAdmin = in_array(UserRole::Admin->value, $currentRoles, true)
            && ! in_array(UserRole::Admin->value, $roles, true);

        if ($losesAdmin && $this->adminsOf($tenant) <= 1) {
            throw TeamException::lastAdmin();
        }

        $user->syncRoles(array_values(array_unique($roles)));

        return $user->fresh();
    }

    private function adminsOf(Tenant $tenant): int
    {
        return $tenant->users()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', UserRole::Admin->value)
            ->where('model_has_roles.tenant_id', $tenant->id)
            ->count();
    }
}
