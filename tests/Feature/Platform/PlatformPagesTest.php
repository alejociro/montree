<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function platformPages(): array
    {
        return [
            'faq' => ['faq', 'Faq'],
            'payment policy' => ['politica-de-pago', 'Policies/Payment'],
            'platform terms' => ['terminos-y-condiciones', 'Policies/PlatformTerms'],
            'privacy policy' => ['politica-de-privacidad', 'Policies/Privacy'],
            'cookie policy' => ['politica-de-cookies', 'Policies/Cookies'],
        ];
    }

    #[DataProvider('platformPages')]
    public function test_platform_page_renders_on_the_apex(string $path, string $component): void
    {
        $response = $this->get('http://montree.test/'.$path);

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component($component));
    }

    #[DataProvider('platformPages')]
    public function test_platform_page_is_not_served_from_a_tenant_subdomain(string $path): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'eco-adventures']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://eco-adventures.montree.test/'.$path);

        $response->assertNotFound();
    }

    public function test_platform_pages_share_the_configured_legal_entity(): void
    {
        config([
            'montree.legal.name' => 'Montree S.A.S.',
            'montree.legal.nit' => '900.000.000-1',
            'montree.legal.privacy_email' => 'datos@montree.co',
        ]);

        $response = $this->get('http://montree.test/politica-de-privacidad');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('platform.legal.name', 'Montree S.A.S.')
            ->where('platform.legal.nit', '900.000.000-1')
            ->where('platform.legal.privacy_email', 'datos@montree.co')
            ->has('platform.commissionSchedule.currency')
            ->has('platform.commissionSchedule.tiers'));
    }

    public function test_tenant_sites_do_not_receive_the_platform_legal_entity(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'eco-adventures']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://eco-adventures.montree.test/');

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('platform', null));
    }

    public function test_cookie_policy_lists_the_real_session_cookie(): void
    {
        $response = $this->get('http://montree.test/politica-de-cookies');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.cookie', config('session.cookie'))
            ->where('session.lifetimeMinutes', (int) config('session.lifetime')));
    }

    public function test_landing_footer_links_to_every_legal_page(): void
    {
        $links = file_get_contents(resource_path('js/composables/useLegalLinks.ts'));

        foreach (['terminos-y-condiciones', 'politica-de-privacidad', 'politica-de-cookies', 'politica-de-pago'] as $path) {
            $this->assertStringContainsString("'/{$path}'", $links);
            $this->get('http://montree.test/'.$path)->assertOk();
        }
    }

    public function test_the_old_cancellation_policy_url_redirects_to_the_platform_terms(): void
    {
        $this->get('http://montree.test/politica-de-cancelacion')
            ->assertStatus(301)
            ->assertRedirect('/terminos-y-condiciones');
    }

    public function test_default_agency_terms_keep_the_consumer_rights_clause(): void
    {
        $this->assertStringContainsString('Ley 1480 de 2011', file_get_contents(resource_path('policies/terms.es.md')));
        $this->assertStringContainsString('Law 1480 of 2011', file_get_contents(resource_path('policies/terms.en.md')));
    }
}
