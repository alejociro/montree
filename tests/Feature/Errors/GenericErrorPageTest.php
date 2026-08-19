<?php

declare(strict_types=1);

namespace Tests\Feature\Errors;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Regresión de las pruebas con usuarios reales: un 403 de navegación salía como la
 * página cruda de Symfony ("403 Forbidden", en inglés, sin marca y sin ningún enlace
 * de vuelta), así que quien abría un módulo que su rol no tiene quedaba encerrado.
 *
 * Lo que se congela acá: los errores HTTP de página salen como `Errors/Generic` con el
 * status intacto y con el home del rol; las respuestas JSON conservan el shape de
 * `contracts.md` §4; y las páginas específicas que ya existían siguen ganando.
 */
final class GenericErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://error-page.montree.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'error-page',
            'domain' => 'error-page.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_forbidden_page_renders_the_branded_error_with_a_way_back(): void
    {
        $guide = $this->memberFor(UserRole::Guide);

        $response = $this->actingAs($guide)->get(self::HOST.'/admin/tours');

        $response->assertForbidden();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/Generic')
            ->where('status', 403)
            ->where('homeUrl', RoleHomeResolver::GUIDE_HOME)
        );
    }

    public function test_a_missing_page_renders_the_branded_error(): void
    {
        $response = $this->get(self::HOST.'/ruta-que-no-existe');

        $response->assertNotFound();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/Generic')
            ->where('status', 404)
            ->where('homeUrl', '/')
        );
    }

    /**
     * El super_admin no es miembro de ninguna agencia: su salida es el panel de
     * plataforma, no el home de un tenant al que tampoco puede entrar.
     */
    public function test_the_super_admin_is_sent_back_to_the_platform_panel(): void
    {
        $superAdmin = User::factory()->create();
        setPermissionsTeamId(0);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $response = $this->actingAs($superAdmin)->get('http://montree.test/admin/dashboard');

        $response->assertForbidden();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/Generic')
            ->where('homeUrl', '/super-admin/dashboard')
        );
    }

    /**
     * El shape JSON de `contracts.md` §4 manda para las llamadas de la API: la página
     * de error es solo para navegación.
     */
    public function test_json_requests_keep_the_permission_error_shape(): void
    {
        $guide = $this->memberFor(UserRole::Guide);

        $response = $this->actingAs($guide)
            ->getJson(self::HOST.'/api/v1/admin/tours');

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'INSUFFICIENT_PERMISSION');
    }

    private function memberFor(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);
        setPermissionsTeamId(0);

        return $user;
    }
}
