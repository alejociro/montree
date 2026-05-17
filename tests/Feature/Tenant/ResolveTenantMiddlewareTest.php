<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('_test/tenant-context', function () {
            $tenant = Tenant::current();

            return [
                'tenant_id' => $tenant?->id,
                'permissions_team_id' => getPermissionsTeamId(),
            ];
        });
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_resolves_tenant_from_subdomain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'eco-adventures']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://eco-adventures.montree.test/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('tenant_id', $tenant->id);
    }

    public function test_returns_404_inertia_when_subdomain_not_found(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('http://ghost-tenant.montree.test/_test/tenant-context');

        $response->assertStatus(404);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertSame('Errors/TenantNotFound', $response->json('component'));
    }

    public function test_returns_503_inertia_when_tenant_suspended(): void
    {
        $tenant = Tenant::factory()->suspended()->create(['slug' => 'paused']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('http://paused.montree.test/_test/tenant-context');

        $response->assertStatus(503);
        $this->assertSame('Errors/TenantSuspended', $response->json('component'));
        $this->assertSame($tenant->name, $response->json('props.tenantName'));
    }

    public function test_allows_reserved_host_without_tenant(): void
    {
        $response = $this->get('http://www.montree.test/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('tenant_id', null);
    }

    public function test_allows_root_domain_without_tenant(): void
    {
        $response = $this->get('http://montree.test/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('tenant_id', null);
    }

    public function test_sets_permissions_team_id_after_resolution(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'team-scope']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://team-scope.montree.test/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('permissions_team_id', $tenant->id);
    }
}
