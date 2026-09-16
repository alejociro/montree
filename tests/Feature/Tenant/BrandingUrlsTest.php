<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Http\Resources\TenantConfigurationResource;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El logo se guarda siempre en el disco `public`; resolver su URL con el disco
 * por defecto devolvía una ruta que el navegador no podía abrir (spec criterio D).
 */
final class BrandingUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_the_asset_urls_point_at_the_public_disk(): void
    {
        config(['filesystems.default' => 'local']);

        $configuration = $this->configurationWith([
            'logo_path' => 'tenants/1/branding/logo.png',
            'favicon_path' => 'tenants/1/branding/favicon.png',
            'hero_image_path' => 'tenants/1/branding/hero.jpg',
        ]);

        $resolved = $this->resolve($configuration);

        $this->assertStringContainsString('/storage/tenants/1/branding/logo.png', (string) $resolved['logo_url']);
        $this->assertSame(Storage::disk('public')->url('tenants/1/branding/logo.png'), $resolved['logo_url']);
        $this->assertSame(Storage::disk('public')->url('tenants/1/branding/favicon.png'), $resolved['favicon_url']);
        $this->assertSame(Storage::disk('public')->url('tenants/1/branding/hero.jpg'), $resolved['hero_image_url']);
    }

    public function test_an_absolute_url_is_returned_untouched(): void
    {
        $configuration = $this->configurationWith([
            'logo_path' => 'https://cdn.example.com/logo.png',
        ]);

        $this->assertSame(
            'https://cdn.example.com/logo.png',
            $this->resolve($configuration)['logo_url'],
        );
    }

    public function test_a_tenant_without_assets_reports_null_urls(): void
    {
        $configuration = $this->configurationWith([
            'logo_path' => null,
            'favicon_path' => null,
            'hero_image_path' => null,
        ]);

        $resolved = $this->resolve($configuration);

        $this->assertNull($resolved['logo_url']);
        $this->assertNull($resolved['favicon_url']);
        $this->assertNull($resolved['hero_image_url']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function configurationWith(array $attributes): TenantConfiguration
    {
        $tenant = Tenant::factory()->create(['slug' => 'brand', 'domain' => 'brand.montree.test']);
        $tenant->makeCurrent();

        return TenantConfiguration::factory()->for($tenant)->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(TenantConfiguration $configuration): array
    {
        return (new TenantConfigurationResource($configuration))->toArray(Request::create('/'));
    }
}
