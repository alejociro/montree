<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La configuración de la agencia se guarda por ruta web multipart: el admin del
 * tenant sube su propio logo, favicon e imagen principal sin pasar por el super
 * admin (spec criterio D).
 */
final class TenantConfigurationUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_stores_the_branding_assets_and_the_contact_information(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('demo');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->post($this->url($tenant), [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'hero_image' => UploadedFile::fake()->image('hero.jpg'),
            'primary_color' => '#b91c1c',
            'contact_info' => ['address' => 'Calle 10 #4-20', 'whatsapp' => '+57 300 000 0000'],
        ]);

        $response->assertRedirect($this->url($tenant));

        $configuration->refresh();

        Storage::disk('public')->assertExists((string) $configuration->logo_path);
        Storage::disk('public')->assertExists((string) $configuration->hero_image_path);
        $this->assertSame('#b91c1c', $configuration->primary_color);
        $this->assertSame('+57 300 000 0000', $configuration->contact_info['whatsapp'] ?? null);
    }

    public function test_a_color_that_is_not_sent_is_left_untouched(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('keep', [
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['tagline' => 'Aventuras reales'])
            ->assertRedirect($this->url($tenant));

        $configuration->refresh();

        $this->assertSame('#123456', $configuration->primary_color);
        $this->assertSame('#654321', $configuration->secondary_color);
    }

    public function test_remove_logo_deletes_the_stored_file(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('erase');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)->post($this->url($tenant), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $storedPath = (string) $configuration->refresh()->logo_path;

        $this->actingAs($admin)
            ->post($this->url($tenant), ['remove_logo' => true])
            ->assertRedirect($this->url($tenant));

        $this->assertNull($configuration->refresh()->logo_path);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        [$tenant] = $this->tenantWithConfiguration('invalid');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['logo' => UploadedFile::fake()->create('manual.pdf', 40, 'application/pdf')])
            ->assertSessionHasErrors('logo');
    }

    public function test_an_invalid_color_is_rejected(): void
    {
        [$tenant] = $this->tenantWithConfiguration('bad-color');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['primary_color' => 'rojo'])
            ->assertSessionHasErrors('primary_color');
    }

    public function test_a_guide_cannot_update_the_configuration(): void
    {
        [$tenant] = $this->tenantWithConfiguration('no-perm');
        $guide = $this->memberFor($tenant, UserRole::Guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->post($this->url($tenant), ['tagline' => 'No debería entrar'])
            ->assertForbidden();
    }

    public function test_an_admin_cannot_update_the_configuration_of_another_tenant(): void
    {
        [$owner, $ownerConfiguration] = $this->tenantWithConfiguration('owner', ['tagline' => 'Intacta']);
        Tenant::forgetCurrent();
        [$intruder] = $this->tenantWithConfiguration('intruder');
        $intruderAdmin = $this->memberFor($intruder, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($intruderAdmin)
            ->post($this->url($owner), ['tagline' => 'Secuestrada'])
            ->assertForbidden();

        $this->assertSame('Intacta', $ownerConfiguration->refresh()->tagline);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Tenant, 1: TenantConfiguration}
     */
    private function tenantWithConfiguration(string $slug, array $attributes = []): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug, 'domain' => "{$slug}.montree.test"]);
        $configuration = TenantConfiguration::factory()->for($tenant)->create($attributes);
        $tenant->makeCurrent();

        return [$tenant, $configuration];
    }

    private function url(Tenant $tenant): string
    {
        return "http://{$tenant->domain}/admin/tenant/configuration";
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        Role::findOrCreate($role->value, 'web');

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
