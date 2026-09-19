<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin' => ['categories.view', 'categories.manage'],
        'operator' => ['categories.view', 'categories.manage'],
        'sales' => ['categories.view'],
    ];

    public function up(): void
    {
        $permissionIds = [];

        foreach (['categories.view', 'categories.manage'] as $name) {
            $permissionIds[$name] = $this->permissionId($name);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $names) {
            // WHY: los roles base viven con `tenant_id` nulo y los comparten todos los
            // tenants (RolesAndPermissionsSeeder). Los roles propios de una agencia no
            // se tocan: su juego de permisos lo elige quien los creó.
            $roleId = DB::table('roles')
                ->where('name', $role)
                ->where('guard_name', self::GUARD)
                ->whereNull('tenant_id')
                ->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach ($names as $name) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds[$name],
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', ['categories.view', 'categories.manage'])->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(string $name): int
    {
        $existing = DB::table('permissions')->where('name', $name)->where('guard_name', self::GUARD)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => $name,
            'guard_name' => self::GUARD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
