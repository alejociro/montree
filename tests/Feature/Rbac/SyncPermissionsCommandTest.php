<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `montree:sync-permissions` es el remedio de despliegue: publica el catálogo y la
 * matriz rol → permiso sin arrastrar `DemoTenantSeeder`, y adopta las asignaciones
 * que quedaron sin `tenant_id` (invisibles con `teams => true`).
 */
final class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_it_publishes_the_catalog_and_the_role_permission_matrix(): void
    {
        DB::table('role_has_permissions')->delete();
        DB::table('permissions')->delete();

        $this->artisan('montree:sync-permissions')->assertSuccessful();

        $this->assertSame(
            PermissionCatalogSeederTest::CATALOG_SIZE,
            DB::table('permissions')->count(),
        );
        $this->assertGreaterThan(0, DB::table('role_has_permissions')->count());
    }

    public function test_it_does_not_seed_demo_data(): void
    {
        $this->artisan('montree:sync-permissions')->assertSuccessful();

        $this->assertDatabaseMissing('tenants', ['slug' => 'demo']);
    }

    public function test_it_assigns_a_role_to_a_member_that_has_none(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $user = User::factory()->create(['email' => 'huerfano@demo.test']);
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        $this->artisan('montree:sync-permissions', [
            '--tenant' => ['demo'],
            '--assign' => ['huerfano@demo.test:admin'],
        ])->assertSuccessful();

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');

        $this->assertSame([UserRole::Admin->value], $user->getRoleNames()->all());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $user = User::factory()->create(['email' => 'huerfano@demo.test']);
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        $this->artisan('montree:sync-permissions', [
            '--tenant' => ['demo'],
            '--assign' => ['huerfano@demo.test:admin'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $user->id)->count());
    }

    public function test_it_reports_a_member_without_any_role_in_the_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $user = User::factory()->create(['email' => 'huerfano@demo.test']);
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        $this->artisan('montree:sync-permissions', ['--tenant' => ['demo']])
            ->expectsOutputToContain('SIN ROL')
            ->assertSuccessful();
    }

    public function test_it_succeeds_on_a_fresh_deployment_without_tenants(): void
    {
        $this->artisan('montree:sync-permissions')->assertSuccessful();
    }

    public function test_it_fails_when_the_tenant_does_not_exist(): void
    {
        $this->artisan('montree:sync-permissions', ['--tenant' => ['no-existe']])->assertFailed();
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
