<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantPendingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_pending_subdomain_shows_pending_page_not_catalog(): void
    {
        $tenant = Tenant::factory()->pending()->create(['slug' => 'incoming']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('http://incoming.montree.test/');

        $response->assertStatus(503);
        $this->assertSame('Errors/TenantPending', $response->json('component'));
        $this->assertSame($tenant->name, $response->json('props.tenantName'));
    }

    public function test_active_subdomain_is_not_blocked(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'live']);
        TenantConfiguration::factory()->for($tenant)->create();

        $this->get('http://live.montree.test/')->assertOk();
    }
}
