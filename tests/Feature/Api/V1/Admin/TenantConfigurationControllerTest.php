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

    public function test_admin_can_configure_its_own_placetopay_merchant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            [
                'placetopay_login' => 'tenant-login',
                'placetopay_tran_key' => 'tenant-tran-key',
                'placetopay_url' => 'https://checkout.placetopay.ec',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.configuration.placetopay.login', 'tenant-login');
        $response->assertJsonPath('data.configuration.placetopay.url', 'https://checkout.placetopay.ec');
        $response->assertJsonPath('data.configuration.placetopay.tran_key_set', true);
        // El tranKey nunca vuelve al panel.
        $response->assertJsonMissing(['tran_key' => 'tenant-tran-key']);

        $this->assertSame('tenant-tran-key', $tenant->configuration->fresh()->placetopay_tran_key);
    }

    public function test_saving_without_a_new_tran_key_keeps_the_stored_one(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $configuration = TenantConfiguration::factory()->for($tenant)->create([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
        ]);
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['placetopay_login' => 'tenant-login', 'placetopay_url' => 'https://checkout.placetopay.com'],
        )->assertOk();

        $this->assertSame('tenant-tran-key', $configuration->fresh()->placetopay_tran_key);
    }

    public function test_clearing_the_login_disables_the_tenant_merchant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $configuration = TenantConfiguration::factory()->for($tenant)->create([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
            'placetopay_url' => 'https://checkout.placetopay.ec',
        ]);
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['placetopay_login' => null],
        )->assertOk();

        $configuration->refresh();

        $this->assertNull($configuration->placetopay_login);
        $this->assertNull($configuration->placetopay_tran_key);
        $this->assertNull($configuration->placetopay_url);
    }

    public function test_a_new_login_without_tran_key_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['placetopay_login' => 'tenant-login'],
        )->assertStatus(422)->assertJsonValidationErrors('placetopay_tran_key');
    }

    public function test_an_http_checkout_url_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            [
                'placetopay_login' => 'tenant-login',
                'placetopay_tran_key' => 'tenant-tran-key',
                'placetopay_url' => 'http://checkout.placetopay.com',
            ],
        )->assertStatus(422)->assertJsonValidationErrors('placetopay_url');
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

    public function test_admin_can_save_its_own_terms(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create(['terms_body' => null]);
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['terms_body' => "## Cancelaciones\n\nHasta 15 días antes."],
        );

        $response->assertOk();
        $response->assertJsonPath('data.configuration.terms_body', "## Cancelaciones\n\nHasta 15 días antes.");
        $response->assertJsonPath('data.configuration.terms_is_default', false);

        $this->assertSame("## Cancelaciones\n\nHasta 15 días antes.", $tenant->configuration->fresh()?->terms_body);
    }

    public function test_blank_terms_go_back_to_the_default_text(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $configuration = TenantConfiguration::factory()->for($tenant)->create(['terms_body' => '## Los míos']);
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['terms_body' => "   \n\t "],
        );

        $response->assertOk();
        $response->assertJsonPath('data.configuration.terms_body', null);
        $response->assertJsonPath('data.configuration.terms_is_default', true);

        $this->assertNull($configuration->fresh()?->terms_body);
    }

    public function test_terms_longer_than_the_limit_are_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['terms_body' => str_repeat('a', 20001)],
        )->assertStatus(422)->assertJsonValidationErrors('terms_body');
    }

    public function test_a_member_without_the_settings_permission_cannot_save_terms(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        Role::findOrCreate(UserRole::Guide->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole(UserRole::Guide->value);

        $this->actingAs($user)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant/configuration',
            ['terms_body' => '## Intento'],
        )->assertForbidden();
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
