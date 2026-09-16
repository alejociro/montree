<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\TourStatus;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Criterio H: el producto no tiene moneda propia. El catálogo y el detalle
 * público dejan de emitirla y la pantalla formatea con la del tenant, así que
 * cambiarla se refleja sin tocar un solo producto.
 */
final class TenantCurrencyDrivesPricesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private TenantConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->configuration = TenantConfiguration::factory()->for($this->tenant)->create(['currency' => 'COP']);
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_the_catalog_never_emits_the_currency_of_a_product(): void
    {
        Tour::factory()->active()->create(['slug' => 'cocora', 'currency' => 'COP']);

        $this->getJson('http://demo.montree.test/api/v1/tours')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'cocora')
            ->assertJsonMissingPath('data.0.currency');
    }

    public function test_the_public_detail_never_emits_the_currency_of_a_product(): void
    {
        Tour::factory()->create(['slug' => 'cocora', 'status' => TourStatus::Active, 'currency' => 'COP']);

        $this->get('http://demo.montree.test/tours/cocora')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TourDetail')
                ->where('tour.slug', 'cocora')
                ->missing('tour.currency')
            );
    }

    public function test_changing_the_tenant_currency_changes_the_shared_prop_that_formats_every_price(): void
    {
        Tour::factory()->active()->create(['currency' => 'COP']);

        $this->configuration->update(['currency' => 'USD']);
        $this->tenant->unsetRelation('configuration');

        $this->get('http://demo.montree.test/tours')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('tenantConfiguration.currency', 'USD')
            );
    }
}
