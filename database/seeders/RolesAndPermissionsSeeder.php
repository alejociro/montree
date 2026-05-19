<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->updateOrCreate(
            ['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web', 'tenant_id' => null],
        );

        foreach ([UserRole::Admin, UserRole::Operator, UserRole::Guide, UserRole::Customer] as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value, 'guard_name' => 'web', 'tenant_id' => null],
            );
        }
    }
}
