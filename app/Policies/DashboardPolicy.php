<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class DashboardPolicy
{
    public function view(User $user): bool
    {
        // WHY: spatie/permission with teams=true is already scoped by ResolveTenant
        // middleware via setPermissionsTeamId($tenant->id); hasRole reads from that scope.
        if ($user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(UserRole::Admin->value)) {
            return true;
        }

        return $user->hasRole(UserRole::Operator->value);
    }

    public function exportReports(User $user): bool
    {
        if ($user->hasRole(UserRole::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(UserRole::Admin->value);
    }
}
