<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Exceptions\RoleException;
use App\Models\Tenant;
use App\Services\Rbac\TenantRoleCatalog;
use Spatie\Permission\Models\Role;

final class DeleteTenantRoleAction
{
    public function handle(Tenant $tenant, Role $role): void
    {
        if (TenantRoleCatalog::isBase($role)) {
            throw RoleException::baseRoleIsReadOnly();
        }

        $assigned = $role->users()
            ->where('model_has_roles.tenant_id', $tenant->getKey())
            ->count();

        if ($assigned > 0) {
            throw RoleException::inUse($assigned);
        }

        $role->delete();
    }
}
