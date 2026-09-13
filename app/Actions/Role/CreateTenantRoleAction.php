<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

final class CreateTenantRoleAction
{
    /**
     * @param  array{name: string, description?: string|null, permissions: array<int, string>}  $data
     */
    public function handle(Tenant $tenant, array $data): Role
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => trim($data['name']),
            'description' => self::description($data['description'] ?? null),
            'guard_name' => RolesAndPermissionsSeeder::GUARD,
            'tenant_id' => $tenant->getKey(),
        ]);

        $role->syncPermissions($data['permissions']);

        return $role->load('permissions');
    }

    /** Una descripción en blanco es no tener descripción, no una cadena vacía. */
    public static function description(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
