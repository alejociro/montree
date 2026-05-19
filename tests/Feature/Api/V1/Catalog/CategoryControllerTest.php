<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_index_returns_active_categories_with_at_least_one_active_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $hiking = Category::factory()->create(['name' => 'Hiking', 'slug' => 'hiking', 'display_order' => 1]);
        $diving = Category::factory()->create(['name' => 'Diving', 'slug' => 'diving', 'display_order' => 2]);
        Category::factory()->create(['name' => 'Empty', 'slug' => 'empty', 'display_order' => 0]);
        Category::factory()->inactive()->create(['name' => 'Hidden', 'slug' => 'hidden']);

        Tour::factory()->active()->create(['category_id' => $hiking->id]);
        Tour::factory()->active()->create(['category_id' => $hiking->id]);
        Tour::factory()->active()->create(['category_id' => $diving->id]);
        Tour::factory()->create(['category_id' => $diving->id, 'status' => TourStatus::Draft]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/categories');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.slug', 'hiking');
        $response->assertJsonPath('data.0.tours_count', 2);
        $response->assertJsonPath('data.1.slug', 'diving');
        $response->assertJsonPath('data.1.tours_count', 1);
    }

    public function test_tenant_isolation_does_not_leak_categories(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $catA = Category::factory()->create(['slug' => 'alpha-cat']);
        Tour::factory()->active()->create(['category_id' => $catA->id]);

        $tenantB->makeCurrent();
        $catB = Category::factory()->create(['slug' => 'bravo-cat']);
        Tour::factory()->active()->create(['category_id' => $catB->id]);

        Tenant::forgetCurrent();
        $response = $this->getJson('http://alpha.montree.test/api/v1/tours/categories');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', 'alpha-cat');
    }

    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }
}
