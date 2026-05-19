<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_super_admin_updates_configuration_fields(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->actingAs($this->superAdmin())->postJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/configuration",
            [
                'primary_color' => '#16a34a',
                'tagline' => 'Aventura sostenible',
                'currency' => 'COP',
                'reviews_require_moderation' => true,
            ],
        );

        $response->assertOk();
        $this->assertSame('#16a34a', $tenant->configuration->fresh()->primary_color);
        $this->assertSame('Aventura sostenible', $tenant->configuration->fresh()->tagline);
    }

    public function test_super_admin_uploads_branding_files(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->actingAs($this->superAdmin())->post(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/configuration",
            [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
                'hero_image' => UploadedFile::fake()->image('hero.jpg', 1200, 600),
            ],
            ['Accept' => 'application/json'],
        );

        $response->assertOk();
        $configuration = $tenant->configuration->fresh();
        $this->assertNotNull($configuration->logo_path);
        $this->assertNotNull($configuration->hero_image_path);
        Storage::disk('public')->assertExists($configuration->logo_path);
        Storage::disk('public')->assertExists($configuration->hero_image_path);
    }

    public function test_invalid_color_returns_validation_error(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->superAdmin())->postJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/configuration",
            ['primary_color' => 'not-a-color'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('primary_color');
    }

    public function test_non_super_admin_cannot_update_configuration(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/configuration",
            ['tagline' => 'hack'],
        );

        $response->assertForbidden();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }
}
