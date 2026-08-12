<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PlatformHostRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_admin_alias_host_redirects_to_the_platform_apex(): void
    {
        $response = $this->get('http://admin.montree.test/');

        $response->assertRedirect('http://montree.test/');
        $response->assertStatus(301);
    }

    public function test_alias_redirect_preserves_path_and_query_string(): void
    {
        $response = $this->get('http://admin.montree.test/start?ref=clickup');

        $response->assertRedirect('http://montree.test/start?ref=clickup');
    }

    public function test_admin_alias_redirects_even_when_not_listed_as_reserved(): void
    {
        Config::set('montree.reserved_hosts', 'montree.test,localhost');

        $response = $this->get('http://admin.montree.test/');

        $response->assertRedirect('http://montree.test/');
    }

    public function test_platform_apex_is_not_redirected(): void
    {
        $response = $this->get('http://montree.test/');

        $response->assertOk();
    }

    public function test_tenant_subdomain_is_not_redirected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'eco-adventures']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://eco-adventures.montree.test/');

        $response->assertOk();
    }
}
