<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PlaceToPayEnvironment;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Tenant\TermsRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
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

    /**
     * T5: los planes desaparecieron del producto, así que el CSS
     * personalizado ya no exige "Enterprise" — cualquier agencia lo guarda.
     */
    public function test_custom_css_is_accepted_for_any_tenant(): void
    {
        [$tenant] = $this->tenantWithConfiguration('any-plan');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->post($this->url($tenant), [
            'custom_css' => '.hero { color: red; }',
        ]);

        $response->assertRedirect($this->url($tenant));
        $response->assertSessionDoesntHaveErrors('custom_css');
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

    /**
     * T12: cierre de reservas por defecto de la agencia, en horas antes del
     * inicio de cada salida. Vacío/`null` = sin regla.
     */
    public function test_booking_advance_hours_is_saved_and_can_be_cleared(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('advance-hours');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['booking_advance_hours' => 24])
            ->assertRedirect($this->url($tenant));

        $this->assertSame(24, $configuration->refresh()->booking_advance_hours);

        $this->actingAs($admin)
            ->post($this->url($tenant), ['booking_advance_hours' => null])
            ->assertRedirect($this->url($tenant));

        $this->assertNull($configuration->refresh()->booking_advance_hours);
    }

    public function test_booking_advance_hours_out_of_range_is_rejected(): void
    {
        [$tenant] = $this->tenantWithConfiguration('advance-hours-invalid');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['booking_advance_hours' => 721])
            ->assertSessionHasErrors('booking_advance_hours');
    }

    /**
     * T13: sin términos propios, la página envía el texto por defecto
     * vigente para que el editor arranque precargado (no vacío).
     */
    public function test_the_configuration_page_sends_the_default_terms_body(): void
    {
        [$tenant] = $this->tenantWithConfiguration('terms-default');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $defaultBody = app(TermsRenderer::class)->defaultBody();

        $this->actingAs($admin)
            ->get('http://'.$tenant->domain.'/admin/tenant/configuration')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('terms.body', null)
                ->where('terms.is_default', true)
                ->where('terms.default_body', $defaultBody)
            );
    }

    /**
     * T13: guardar el texto por defecto sin cambios (más allá de fin de
     * línea o espacios al inicio/fin) deja `terms_body` en `null`: la
     * agencia sigue "usando el por defecto" y recibe futuras mejoras de la
     * plantilla.
     */
    public function test_saving_the_default_terms_body_unchanged_stores_null(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('terms-unchanged', ['terms_body' => 'Algo propio previo']);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $defaultBody = app(TermsRenderer::class)->defaultBody();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['terms_body' => "  {$defaultBody}\n\n"])
            ->assertRedirect($this->url($tenant));

        $this->assertNull($configuration->refresh()->terms_body);
    }

    /**
     * T13: un texto distinto del por defecto se guarda como propio.
     */
    public function test_saving_a_modified_terms_body_stores_it_as_own(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('terms-own');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['terms_body' => '## Cancelaciones propias de esta agencia'])
            ->assertRedirect($this->url($tenant));

        $this->assertSame('## Cancelaciones propias de esta agencia', $configuration->refresh()->terms_body);
    }

    /**
     * T13: "Restaurar texto por defecto" es, del lado del servidor, enviar
     * el texto por defecto de vuelta — vuelve a `null` aunque la agencia
     * tuviera unos propios guardados.
     */
    public function test_restoring_the_default_terms_body_sets_it_back_to_null(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('terms-restore', ['terms_body' => '## Propios de la agencia']);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $defaultBody = app(TermsRenderer::class)->defaultBody();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['terms_body' => $defaultBody])
            ->assertRedirect($this->url($tenant));

        $this->assertNull($configuration->refresh()->terms_body);
    }

    /**
     * T14: la agencia elige el ambiente de PlacetoPay (test|production) en
     * vez de escribir la URL del checkout a mano.
     */
    public function test_the_placetopay_environment_is_saved(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('p2p-env');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), [
                'placetopay_login' => 'agency-login',
                'placetopay_tran_key' => 'agency-tran-key',
                'placetopay_environment' => 'production',
            ])
            ->assertRedirect($this->url($tenant));

        $configuration->refresh();

        $this->assertSame('agency-login', $configuration->placetopay_login);
        $this->assertSame(PlaceToPayEnvironment::Production, $configuration->placetopay_environment);
    }

    public function test_an_invalid_placetopay_environment_is_rejected(): void
    {
        [$tenant] = $this->tenantWithConfiguration('p2p-env-invalid');
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['placetopay_environment' => 'staging'])
            ->assertSessionHasErrors('placetopay_environment');
    }

    /**
     * T14: apagar el comercio propio (login vacío) vuelve el ambiente al
     * default ('test'), coherente con el respaldo de plataforma.
     */
    public function test_clearing_the_merchant_login_resets_the_environment_to_test(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('p2p-env-reset', [
            'placetopay_login' => 'agency-login',
            'placetopay_tran_key' => 'agency-tran-key',
            'placetopay_environment' => PlaceToPayEnvironment::Production,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->post($this->url($tenant), ['placetopay_login' => ''])
            ->assertRedirect($this->url($tenant));

        $configuration->refresh();

        $this->assertNull($configuration->placetopay_login);
        $this->assertSame(PlaceToPayEnvironment::Test, $configuration->placetopay_environment);
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
