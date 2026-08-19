<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Corte de `operator` → `sales` + `operator` (F018 spec.md §"Migración de operator"):
 * nadie pierde acceso el día del despliegue.
 */
final class RoleMigrationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_existing_operator_also_receives_the_sales_role(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->seed(RolesAndPermissionsSeeder::class);

        setPermissionsTeamId($tenant->id);
        $operator->unsetRelation('roles');

        $this->assertEqualsCanonicalizing(
            [UserRole::Sales->value, UserRole::Operator->value],
            $operator->getRoleNames()->all(),
        );
    }

    public function test_user_without_the_operator_role_is_left_untouched(): void
    {
        $tenant = Tenant::factory()->create();
        $guide = $this->memberFor($tenant, UserRole::Guide);

        $this->seed(RolesAndPermissionsSeeder::class);

        setPermissionsTeamId($tenant->id);
        $guide->unsetRelation('roles');

        $this->assertSame([UserRole::Guide->value], $guide->getRoleNames()->all());
    }

    public function test_cut_routine_does_not_duplicate_the_sales_role_when_run_twice(): void
    {
        $tenant = Tenant::factory()->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        setPermissionsTeamId($tenant->id);
        $operator->unsetRelation('roles');

        $this->assertCount(2, $operator->getRoleNames());
    }

    public function test_cut_routine_keeps_each_tenant_assignment_on_its_own_team(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $operator = $this->memberFor($tenantA, UserRole::Operator);
        $tenantB->users()->attach($operator->id, ['status' => 'active', 'joined_at' => now()]);

        $this->seed(RolesAndPermissionsSeeder::class);

        setPermissionsTeamId($tenantB->id);
        $operator->unsetRelation('roles');

        $this->assertSame([], $operator->getRoleNames()->all());
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
