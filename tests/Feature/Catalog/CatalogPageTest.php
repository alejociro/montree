<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_catalog_page_renders_and_exposes_initial_filters(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();
        Tour::factory()->active()->create();

        $response = $this->get('http://demo.montree.test/tours?sort=price_asc');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Catalog')
            ->where('filters.sort', 'price_asc')
            ->where('filters.search', null)
        );
    }

    public function test_catalog_page_rejects_invalid_sort_param(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        $response = $this->get('http://demo.montree.test/tours?sort=invalid', [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
    }
}
