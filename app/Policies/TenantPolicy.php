<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function update(User $user, Tenant $tenant): bool
    {
        // WHY: spatie/permission with teams=true is already scoped by ResolveTenant
        // middleware via setPermissionsTeamId($tenant->id); hasRole reads from that scope.
        if ($user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(UserRole::Admin->value);
    }
}
