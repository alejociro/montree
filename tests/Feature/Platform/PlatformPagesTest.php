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
            'cancellation policy' => ['politica-de-cancelacion', 'Policies/Cancellation'],
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
}
