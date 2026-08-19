<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Exceptions\RoleException;
use App\Services\Rbac\TenantRoleCatalog;
use Spatie\Permission\Models\Role;

final class UpdateTenantRoleAction
{
    /**
     * @param  array{name?: string, permissions?: array<int, string>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        if (TenantRoleCatalog::isBase($role)) {
            throw RoleException::baseRoleIsReadOnly();
        }

        if (isset($data['name'])) {
            $role->update(['name' => trim($data['name'])]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }
}
