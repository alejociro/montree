<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantSharedPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('_test/shared', fn () => Inertia::render('Welcome', []));
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_inertia_pages_receive_tenant_and_configuration(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
            'name' => 'Demo Eco',
        ]);
        TenantConfiguration::factory()->for($tenant)->create([
            'primary_color' => '#16a34a',
            'secondary_color' => '#0f766e',
        ]);

        $response = $this->get('http://demo.montree.test/_test/shared');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('tenant.id', $tenant->id)
            ->where('tenant.slug', 'demo')
            ->where('tenant.name', 'Demo Eco')
            ->where('tenantConfiguration.primary_color', '#16a34a')
            ->whereNot('tenantConfiguration.primary_color_hsl', null)
        );
    }
}
