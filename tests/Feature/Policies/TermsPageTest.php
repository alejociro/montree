<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // WHY: la page Vue la construye el frontend; el backend solo verifica props.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_agency_own_terms_are_rendered_on_its_subdomain(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', "## Cancelaciones\n\nPodés cancelar con **15 días**.\n\n- Sin costo\n- Sin excusas");

        $response = $this->get('http://demo.montree.test/terminos');

        $response->assertOk();

        // La page ya existe, así que el helper de Inertia puede resolverla: esto
        // verifica el componente además de las props.
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Policies/Terms')
            ->where('agency.name', 'Demo Eco Adventures')
            ->where('terms.is_default', false)
            ->whereNot('terms.updated_at', null)
            ->where('terms.html', fn (string $html): bool => str_contains($html, '<h2>Cancelaciones</h2>')
                && str_contains($html, '<strong>15 días</strong>')
                && str_contains($html, '<li>Sin costo</li>'))
        );
    }

    public function test_an_agency_without_its_own_terms_falls_back_to_the_default_text(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', null);

        $response = $this->withUnencryptedCookie('locale', 'es')
            ->get('http://demo.montree.test/terminos');

        $response->assertOk();

        $page = $this->page($response);

        $this->assertSame('Policies/Terms', $page['component']);
        $this->assertTrue($page['props']['terms']['is_default']);
        $this->assertNull($page['props']['terms']['updated_at']);
        $this->assertStringContainsString(
            'Cancelaciones por parte del viajero',
            (string) $page['props']['terms']['html'],
        );
    }

    public function test_the_default_text_follows_the_active_locale(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', null);

        $html = (string) $this->withUnencryptedCookie('locale', 'en')
            ->get('http://demo.montree.test/terminos')
            ->viewData('page')['props']['terms']['html'];

        $this->assertStringContainsString('Cancellations by the traveler', $html);
        $this->assertStringNotContainsString('Cancelaciones por parte del viajero', $html);
    }

    public function test_blank_terms_are_treated_as_not_customised(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', "   \n\t ");

        $response = $this->get('http://demo.montree.test/terminos');

        $response->assertOk();
        $this->assertTrue($this->page($response)['props']['terms']['is_default']);
    }

    public function test_each_agency_sees_its_own_terms(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', '## Términos de Demo');
        $this->makeTenant('otra', 'Otra Agencia', '## Términos de Otra');

        $demo = (string) $this->get('http://demo.montree.test/terminos')
            ->viewData('page')['props']['terms']['html'];

        Tenant::forgetCurrent();

        $otra = (string) $this->get('http://otra.montree.test/terminos')
            ->viewData('page')['props']['terms']['html'];

        $this->assertStringContainsString('Términos de Demo', $demo);
        $this->assertStringNotContainsString('Términos de Otra', $demo);
        $this->assertStringContainsString('Términos de Otra', $otra);
        $this->assertStringNotContainsString('Términos de Demo', $otra);
    }

    public function test_the_page_is_not_served_on_the_platform_host(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', '## Términos de Demo');

        $this->get('http://montree.test/terminos')->assertNotFound();
    }

    public function test_the_checkout_link_target_resolves_on_the_tenant_host(): void
    {
        $this->makeTenant('demo', 'Demo Eco Adventures', null);

        $this->get('http://demo.montree.test/booking/new');

        $this->assertSame('http://demo.montree.test/terminos', route('policies.terms'));
    }

    public function test_dangerous_markup_never_reaches_the_browser(): void
    {
        $body = <<<'MD'
            ## Términos

            <script>alert('xss')</script>
            <img src=x onerror="alert('xss')">

            [click](javascript:alert(1))

            [nuestra web](https://demo.example.com/legal)
            MD;

        $this->makeTenant('demo', 'Demo Eco Adventures', $body);

        $html = (string) $this->get('http://demo.montree.test/terminos')
            ->viewData('page')['props']['terms']['html'];

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        // Control positivo: sin esto el test pasaría aunque el render devolviera vacío.
        $this->assertStringContainsString('<a href="https://demo.example.com/legal">nuestra web</a>', $html);
        $this->assertStringContainsString('<h2>Términos</h2>', $html);
    }

    /**
     * El cuerpo crudo admite 20.000 caracteres y `tenantConfiguration` viaja en
     * TODA respuesta Inertia, catálogo público incluido. Solo lo recibe la
     * pantalla que lo edita.
     */
    public function test_the_raw_terms_never_travel_in_the_shared_props(): void
    {
        $tenant = $this->makeTenant('demo', 'Demo Eco Adventures', '## Secreto a voces');

        $shared = $this->page($this->withoutVite()->get('http://demo.montree.test/terminos'))['props'];

        $this->assertArrayNotHasKey('terms_body', $shared['tenantConfiguration']);
        $this->assertArrayNotHasKey('terms_is_default', $shared['tenantConfiguration']);
        $this->assertStringNotContainsString('## Secreto a voces', json_encode($shared['tenantConfiguration']));
    }

    /**
     * @return array{component: string, props: array<string, mixed>}
     */
    private function page(TestResponse $response): array
    {
        return $response->viewData('page');
    }

    private function makeTenant(string $slug, string $name, ?string $termsBody): Tenant
    {
        $tenant = Tenant::factory()->create([
            'slug' => $slug,
            'name' => $name,
            'domain' => "{$slug}.montree.test",
        ]);

        TenantConfiguration::factory()->for($tenant)->create(['terms_body' => $termsBody]);

        return $tenant;
    }
}
