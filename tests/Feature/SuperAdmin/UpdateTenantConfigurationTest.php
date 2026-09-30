<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateTenantConfigurationTest extends SuperAdminTestCase
{
    public function test_super_admin_updates_the_configuration_fields(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), [
                'primary_color' => '#16a34a',
                'tagline' => 'Aventura sostenible',
                'currency' => 'COP',
                'reviews_require_moderation' => true,
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $configuration = $tenant->configuration->fresh();
        $this->assertSame('#16a34a', $configuration->primary_color);
        $this->assertSame('Aventura sostenible', $configuration->tagline);
    }

    public function test_super_admin_uploads_the_branding_files(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
                'hero_image' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $configuration = $tenant->configuration->fresh();
        Storage::disk('public')->assertExists($configuration->logo_path);
        Storage::disk('public')->assertExists($configuration->hero_image_path);
    }

    /**
     * T12: el super admin también puede fijar la regla de cierre de
     * reservas por defecto de la agencia.
     */
    public function test_super_admin_updates_the_booking_advance_hours_rule(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), [
                'booking_advance_hours' => 48,
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $this->assertSame(48, $tenant->configuration->fresh()->booking_advance_hours);
    }

    public function test_an_invalid_color_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), [
                'primary_color' => 'not-a-color',
            ])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_a_regular_user_cannot_update_the_configuration(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), ['tagline' => 'hack'])
            ->assertForbidden();
    }
}
