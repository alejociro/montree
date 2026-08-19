<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Un miembro del equipo puede tener varios roles a la vez (F018 rbacbase.md §5:
 * "sales + operator"); el endpoint sincroniza el juego completo, no un rol suelto.
 */
final class MultiRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_assigns_two_roles_at_once(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($admin)->patchJson($this->url($member), [
            'roles' => [UserRole::Sales->value, UserRole::Operator->value],
        ]);

        $response->assertOk();

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertSame(['operator', 'sales'], $member->getRoleNames()->sort()->values()->all());
    }

    public function test_the_permissions_of_a_member_are_the_union_of_their_roles(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $this->actingAs($admin)->patchJson($this->url($member), [
            'roles' => [UserRole::Sales->value, UserRole::Operator->value],
        ])->assertOk();

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');

        $this->assertTrue($member->can('bookings.view'));
        $this->assertTrue($member->can('logistics.manage'));
        $this->assertFalse($member->can('team.invite'));
    }

    public function test_accepts_a_role_the_agency_created_for_itself(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);
        $this->tenantRole($tenant, 'coordinacion');

        $response = $this->actingAs($admin)->patchJson($this->url($member), [
            'roles' => [UserRole::Sales->value, 'coordinacion'],
        ]);

        $response->assertOk();

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertSame(['coordinacion', 'sales'], $member->getRoleNames()->sort()->values()->all());
    }

    public function test_rejects_a_role_that_does_not_exist(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($admin)->patchJson($this->url($member), [
            'roles' => ['pirata'],
        ]);

        $response->assertStatus(422);
        $this->assertSame(
            ['Ese rol no existe en tu agencia.'],
            $response->json('errors')['roles.0'],
        );
    }

    public function test_rejects_a_role_that_belongs_to_another_agency(): void
    {
        $tenant = $this->makeTenant();
        $otherTenant = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $this->tenantRole($otherTenant, 'coordinacion');

        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->patchJson($this->url($member), [
            'roles' => ['coordinacion'],
        ]);

        $response->assertStatus(422);

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertSame(['guide'], $member->getRoleNames()->all());
    }

    public function test_rejects_leaving_a_member_without_any_role(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($admin)->patchJson($this->url($member), ['roles' => []]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.roles.0', 'Elige al menos un rol.');

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertSame(['guide'], $member->getRoleNames()->all());
    }

    public function test_still_refuses_to_demote_the_last_admin(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson($this->url($admin), [
            'roles' => [UserRole::Sales->value],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TEAM_LAST_ADMIN');
    }

    private function url(User $member): string
    {
        return "http://demo.montree.test/api/v1/admin/users/{$member->id}/role";
    }

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
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    private function tenantRole(Tenant $tenant, string $name): Role
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => RolesAndPermissionsSeeder::GUARD,
            'tenant_id' => $tenant->getKey(),
        ]);

        return $role;
    }
}
