<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\TenantPlan;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Tenant\TenantConfigurationCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantConfigurationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_can_update_branding(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            [
                'primary_color' => '#15803d',
                'secondary_color' => '#0d9488',
                'currency' => 'COP',
                'tagline' => 'Nuevo eslogan',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.configuration.primary_color', '#15803d');
        $response->assertJsonPath('data.configuration.secondary_color', '#0d9488');
        $response->assertJsonPath('data.configuration.tagline', 'Nuevo eslogan');
        $this->assertNotNull($response->json('data.configuration.primary_color_hsl'));
    }

    public function test_custom_css_rejected_when_plan_not_enterprise(): void
    {
        $tenant = Tenant::factory()->basic()->create(['slug' => 'basic-shop', 'domain' => 'basic-shop.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://basic-shop.montree.test/api/v1/admin/tenant/configuration',
            ['custom_css' => '.tenant-banner { color: red; }'],
        );

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'FEATURE_REQUIRES_ENTERPRISE');
    }

    public function test_custom_css_accepted_on_enterprise_plan_and_sanitized(): void
    {
        $tenant = Tenant::factory()->enterprise()->create(['slug' => 'big-co', 'domain' => 'big-co.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $payload = '.tenant-banner { color: #fff; position: absolute; } body { background: red; }';

        $response = $this->actingAs($admin)->putJson(
            'http://big-co.montree.test/api/v1/admin/tenant/configuration',
            ['custom_css' => $payload],
        );

        $response->assertOk();

        $css = (string) $response->json('data.configuration.custom_css');

        $this->assertStringContainsString('color: #fff', $css);
        $this->assertStringNotContainsString('position', $css);
        $this->assertStringNotContainsString('body', $css);
        $this->assertSame(TenantPlan::Enterprise, $tenant->fresh()?->plan);
    }

    public function test_invalidates_cache_on_update(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'cache-test', 'domain' => 'cache-test.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        // Warm cache with the real tenant (now consumed by SubdomainTenantFinder).
        Cache::put(TenantConfigurationCache::key('cache-test'), $tenant, 300);
        $this->assertNotNull(Cache::get(TenantConfigurationCache::key('cache-test')));

        $this->actingAs($admin)->putJson(
            'http://cache-test.montree.test/api/v1/admin/tenant/configuration',
            ['tagline' => 'cache buster'],
        )->assertOk();

        $this->assertNull(Cache::get(TenantConfigurationCache::key('cache-test')));
    }

    private function adminFor(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate(UserRole::Admin->value, 'web');

        setPermissionsTeamId($tenant->id);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }
}
