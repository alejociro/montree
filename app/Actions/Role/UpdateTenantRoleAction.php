<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Exceptions\RoleException;
use App\Services\Rbac\TenantRoleCatalog;
use Spatie\Permission\Models\Role;

final class UpdateTenantRoleAction
{
    /**
     * @param  array{name?: string, description?: string|null, permissions?: array<int, string>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        if (TenantRoleCatalog::isBase($role)) {
            throw RoleException::baseRoleIsReadOnly();
        }

        if (isset($data['name'])) {
            $role->update(['name' => trim($data['name'])]);
        }

        // `array_key_exists` y no `isset`: mandar `null` es borrar la descripción.
        if (array_key_exists('description', $data)) {
            $role->update(['description' => CreateTenantRoleAction::description($data['description'])]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }
}
