<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

final class SuperAdminTenantPolicy
{
    public function manage(User $user, Tenant $tenant): bool
    {
        // WHY: super_admin role is on sentinel team_id=0 (multi-tenancy.md §9.3).
        setPermissionsTeamId(0);
        $user->unsetRelation('roles');

        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
