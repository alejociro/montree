<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\UserRole;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;

final class UpdateMemberRoleAction
{
    public function handle(Tenant $tenant, User $user, UserRole $role): User
    {
        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $currentRoles = $user->getRoleNames()->all();

        if (in_array(UserRole::Admin->value, $currentRoles, true) && $role !== UserRole::Admin) {
            $adminsCount = $tenant->users()
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', UserRole::Admin->value)
                ->where('model_has_roles.tenant_id', $tenant->id)
                ->count();

            if ($adminsCount <= 1) {
                throw TeamException::lastAdmin();
            }
        }

        $user->syncRoles([$role->value]);

        return $user->fresh();
    }
}
