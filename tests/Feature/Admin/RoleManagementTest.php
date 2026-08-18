<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
 * Módulo de roles y permisos por agencia (F018 fase 3B): roles base de solo lectura
 * más roles propios del tenant, todo detrás de `team.role.update`.
 */
final class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_lists_the_base_roles_and_the_agency_own_roles(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $this->tenantRole($tenant, 'coordinacion', ['bookings.view']);

        $response = $this->actingAs($admin)->getJson($this->url());

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['admin', 'sales', 'operator', 'guide', 'coordinacion'], $names);
        $this->assertNotContains('super_admin', $names);
        $this->assertNotContains('customer', $names);

        $response->assertJsonPath('data.0.label', 'Administrador');
        $response->assertJsonPath('data.0.is_base', true);
        $response->assertJsonPath('data.4.is_base', false);
        $response->assertJsonPath('data.4.permissions_count', 1);
        $response->assertJsonPath('data.4.users_count', 0);
    }

    public function test_counts_only_the_members_of_the_current_agency(): void
    {
        $tenant = $this->makeTenant();
        $otherTenant = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);

        $admin = $this->memberFor($tenant, UserRole::Admin);
        $this->memberFor($otherTenant, UserRole::Admin);

        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->getJson($this->url());

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'admin');
        $response->assertJsonPath('data.0.users_count', 1);
    }

    public function test_shows_a_role_with_its_permissions_grouped_by_module(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $role = $this->tenantRole($tenant, 'coordinacion', ['bookings.view', 'logistics.manage']);

        $response = $this->actingAs($admin)->getJson($this->url("/{$role->getKey()}"));

        $response->assertOk();
        $response->assertJsonPath('data.name', 'coordinacion');
        $response->assertJsonPath('data.is_base', false);
        $response->assertJsonCount(2, 'data.permissions');
        $response->assertJsonPath('data.permissions.0.slug', 'logistics.manage');
        $response->assertJsonPath('data.permissions.0.module', 'logistics');
        $response->assertJsonPath('data.permissions.0.label', 'Gestionar logística');
    }

    public function test_creates_an_agency_own_role_with_a_subset_of_the_catalog(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson($this->url(), [
            'name' => 'Coordinación',
            'permissions' => ['bookings.view', 'departures.view'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Coordinación');
        $response->assertJsonPath('data.is_base', false);
        $response->assertJsonCount(2, 'data.permissions');

        $this->assertDatabaseHas('roles', [
            'name' => 'Coordinación',
            'tenant_id' => $tenant->getKey(),
            'guard_name' => 'web',
        ]);
    }

    public function test_a_member_of_an_agency_own_role_gets_its_permissions(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $this->actingAs($admin)->postJson($this->url(), [
            'name' => 'coordinacion',
            'permissions' => ['bookings.view'],
        ])->assertCreated();

        $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/users/{$member->id}/role",
            ['roles' => ['coordinacion']],
        )->assertOk();

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertTrue($member->can('bookings.view'));
        $this->assertFalse($member->can('bookings.update'));
    }

    public function test_rejects_a_duplicated_name_regardless_of_case(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $this->tenantRole($tenant, 'Coordinación', ['bookings.view']);

        $response = $this->actingAs($admin)->postJson($this->url(), [
            'name' => 'coordinación',
            'permissions' => ['bookings.view'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.name.0', 'Ya tenés un rol con ese nombre.');
    }

    public function test_rejects_a_name_that_collides_with_a_base_role(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson($this->url(), [
            'name' => 'Admin',
            'permissions' => ['bookings.view'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.name.0', 'Ese nombre pertenece a un rol base. Elegí otro.');
    }

    public function test_rejects_a_permission_outside_the_catalog(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson($this->url(), [
            'name' => 'coordinacion',
            'permissions' => ['tours.launch_rocket'],
        ]);

        $response->assertStatus(422);
    }

    public function test_updates_an_agency_own_role(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $role = $this->tenantRole($tenant, 'coordinacion', ['bookings.view']);

        $response = $this->actingAs($admin)->patchJson($this->url("/{$role->getKey()}"), [
            'name' => 'coordinacion-senior',
            'permissions' => ['bookings.view', 'bookings.update'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'coordinacion-senior');
        $response->assertJsonCount(2, 'data.permissions');
    }

    public function test_refuses_to_edit_a_base_role(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $baseRole = Role::query()->where('name', UserRole::Sales->value)->whereNull('tenant_id')->firstOrFail();

        $response = $this->actingAs($admin)->patchJson($this->url("/{$baseRole->getKey()}"), [
            'permissions' => ['bookings.view'],
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'BASE_ROLE_READ_ONLY');
        $this->assertTrue($baseRole->fresh()?->hasPermissionTo('newsletter.send'));
    }

    public function test_refuses_to_delete_a_base_role(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $baseRole = Role::query()->where('name', UserRole::Guide->value)->whereNull('tenant_id')->firstOrFail();

        $response = $this->actingAs($admin)->deleteJson($this->url("/{$baseRole->getKey()}"));

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'BASE_ROLE_READ_ONLY');
        $this->assertModelExists($baseRole);
    }

    public function test_deletes_an_agency_own_role_that_nobody_has(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $role = $this->tenantRole($tenant, 'coordinacion', ['bookings.view']);

        $response = $this->actingAs($admin)->deleteJson($this->url("/{$role->getKey()}"));

        $response->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $role->getKey()]);
    }

    public function test_refuses_to_delete_a_role_in_use_and_says_how_many_have_it(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $role = $this->tenantRole($tenant, 'coordinacion', ['bookings.view']);

        $member = $this->memberFor($tenant, UserRole::Guide);
        setPermissionsTeamId($tenant->id);
        $member->assignRole($role->name);

        $response = $this->actingAs($admin)->deleteJson($this->url("/{$role->getKey()}"));

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'ROLE_IN_USE');
        $this->assertStringContainsString('1 persona', (string) $response->json('message'));
    }

    public function test_the_role_of_another_agency_is_invisible_and_untouchable(): void
    {
        $tenant = $this->makeTenant();
        $otherTenant = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $foreignRole = $this->tenantRole($otherTenant, 'coordinacion', ['bookings.view']);

        Tenant::forgetCurrent();

        $names = collect($this->actingAs($admin)->getJson($this->url())->json('data'))->pluck('name');
        $this->assertNotContains('coordinacion', $names);

        $this->actingAs($admin)->getJson($this->url("/{$foreignRole->getKey()}"))->assertNotFound();
        $this->actingAs($admin)->patchJson($this->url("/{$foreignRole->getKey()}"), ['name' => 'robado'])->assertNotFound();
        $this->actingAs($admin)->deleteJson($this->url("/{$foreignRole->getKey()}"))->assertNotFound();

        $this->assertModelExists($foreignRole);
    }

    public function test_a_member_without_the_permission_cannot_reach_the_module(): void
    {
        $tenant = $this->makeTenant();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $response = $this->actingAs($sales)->getJson($this->url());

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'INSUFFICIENT_PERMISSION');
    }

    public function test_the_index_ships_the_whole_permission_catalog_for_the_form(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson($this->url());

        $response->assertOk();
        $response->assertJsonCount(38, 'meta.available_permissions');
        $response->assertJsonPath('meta.available_permissions.0.slug', 'dashboard.view');
        $response->assertJsonPath('meta.available_permissions.0.module', 'dashboard');
    }

    private function url(string $path = ''): string
    {
        return 'http://demo.montree.test/api/v1/admin/roles'.$path;
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

    /**
     * @param  array<int, string>  $permissions
     */
    private function tenantRole(Tenant $tenant, string $name, array $permissions): Role
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => RolesAndPermissionsSeeder::GUARD,
            'tenant_id' => $tenant->getKey(),
        ]);
        $role->syncPermissions($permissions);

        return $role;
    }
}
