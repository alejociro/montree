<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Andamiaje mínimo de los tests de salidas: un tenant con dominio, un admin y
 * guías que de verdad pertenecen al tenant (la regla de disponibilidad y la de
 * pertenencia se apoyan en el pivote y en el rol, no en el id suelto).
 */
trait DepartureScenario
{
    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($tenant->id);
        Role::findOrCreate($role->value, 'web');
        $user->assignRole($role->value);

        return $user;
    }

    private function guideFor(Tenant $tenant): User
    {
        return $this->memberFor($tenant, UserRole::Guide);
    }

    private function host(Tenant $tenant): string
    {
        return 'http://'.$tenant->domain;
    }
}
