<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_returns_current_tenant_with_configuration(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'name' => 'Demo Eco Adventures',
            'contact_email' => 'hello@demo.montree.test',
            'contact_phone' => '+57 300 000 0000',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($tenant)->create([
            'primary_color' => '#16a34a',
            'secondary_color' => '#0f766e',
        ]);

        $response = $this->get('http://demo.montree.test/api/v1/tenant');

        $response->assertOk();
        $response->assertJsonPath('data.tenant.slug', 'demo');
        $response->assertJsonPath('data.tenant.name', 'Demo Eco Adventures');
        $response->assertJsonPath('data.tenant.status', 'active');
        $response->assertJsonPath('data.tenant.plan', 'professional');
        $response->assertJsonPath('data.configuration.primary_color', '#16a34a');
        $this->assertNotNull($response->json('data.configuration.primary_color_hsl'));
    }

    public function test_returns_404_when_no_tenant_resolved(): void
    {
        // Hitting a reserved host that allows landing without tenant.
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get('http://montree.test/api/v1/tenant');

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'TENANT_NOT_RESOLVED');
    }
}
