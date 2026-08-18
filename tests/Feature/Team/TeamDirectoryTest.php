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
 * Listado de equipo con filtros, búsqueda y paginación (F018 fase 3A).
 */
final class TeamDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_filters_the_team_by_status(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin']);
        $this->memberFor($tenant, UserRole::Guide, ['name' => 'Gil Guía'], TenantMembershipStatus::Suspended);
        $this->memberFor($tenant, UserRole::Sales, ['name' => 'Vera Vendedora'], TenantMembershipStatus::Invited);

        $response = $this->actingAs($admin)->getJson($this->url('?status=invited'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Vera Vendedora');
        $response->assertJsonPath('data.0.status', 'invited');
    }

    public function test_filters_the_team_by_role_including_the_agency_own_roles(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin']);
        $this->memberFor($tenant, UserRole::Guide, ['name' => 'Gil Guía']);

        $ownRole = $this->tenantRole($tenant, 'coordinacion');
        $coordinator = $this->memberFor($tenant, UserRole::Sales, ['name' => 'Coty Coordinadora']);
        setPermissionsTeamId($tenant->id);
        $coordinator->assignRole($ownRole->name);

        $response = $this->actingAs($admin)->getJson($this->url('?role=coordinacion'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Coty Coordinadora');
    }

    public function test_searches_the_team_by_name_or_email(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin', 'email' => 'ana@demo.test']);
        $this->memberFor($tenant, UserRole::Guide, ['name' => 'Gil Guía', 'email' => 'gil@demo.test']);

        $response = $this->actingAs($admin)->getJson($this->url('?search=gil@'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'gil@demo.test');
    }

    public function test_paginates_the_team(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin']);

        for ($i = 0; $i < 4; $i++) {
            $this->memberFor($tenant, UserRole::Guide, ['name' => "Guía {$i}"]);
        }

        $response = $this->actingAs($admin)->getJson($this->url('?per_page=2'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_exposes_the_last_access_and_every_role_of_a_member(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin']);
        $admin->forceFill(['last_login_at' => now()->subDay()])->save();
        setPermissionsTeamId($tenant->id);
        $admin->assignRole(UserRole::Sales->value);

        $response = $this->actingAs($admin)->getJson($this->url('?search=Ana'));

        $response->assertOk();
        $response->assertJsonPath('data.0.last_login_at', now()->subDay()->toIso8601String());

        $roles = collect($response->json('data.0.roles'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['admin', 'sales'], $roles);
    }

    public function test_a_member_with_only_an_agency_own_role_is_listed(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Ana Admin']);

        $ownRole = $this->tenantRole($tenant, 'coordinacion');
        $member = User::factory()->create(['name' => 'Coty Coordinadora']);
        $tenant->users()->attach($member->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        setPermissionsTeamId($tenant->id);
        $member->assignRole($ownRole->name);

        $response = $this->actingAs($admin)->getJson($this->url('?search=Coty'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.roles.0.name', 'coordinacion');
        $response->assertJsonPath('data.0.roles.0.is_base', false);
    }

    public function test_the_filters_do_not_leak_members_of_another_agency(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);

        $admin = $this->memberFor($tenantA, UserRole::Admin, ['name' => 'Ana Admin']);
        $this->memberFor($tenantB, UserRole::Guide, ['name' => 'Ana Ajena']);

        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->getJson($this->url('?search=Ana'));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ana Admin');
    }

    private function url(string $query = ''): string
    {
        return 'http://demo.montree.test/api/v1/admin/users'.$query;
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function memberFor(
        Tenant $tenant,
        UserRole $role,
        array $attrs = [],
        TenantMembershipStatus $status = TenantMembershipStatus::Active,
    ): User {
        $user = User::factory()->create($attrs);
        $tenant->users()->attach($user->id, [
            'status' => $status->value,
            'joined_at' => $status === TenantMembershipStatus::Invited ? null : now(),
            'invited_at' => now(),
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
