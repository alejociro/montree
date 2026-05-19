<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_index_returns_only_active_tours_with_expected_shape(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $category = Category::factory()->create(['name' => 'Senderismo', 'slug' => 'senderismo']);
        $tour = Tour::factory()->active()->create([
            'name' => 'Senderismo Cocora',
            'category_id' => $category->id,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
        ]);
        Tour::factory()->create(['name' => 'Draft tour', 'status' => TourStatus::Draft]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Senderismo Cocora');
        $response->assertJsonPath('data.0.category.slug', 'senderismo');
        $response->assertJsonPath('data.0.has_future_dates', true);
        $response->assertJsonPath('data.0.is_favorite', false);
        $response->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'base_price', 'currency', 'rating_average', 'next_date_starts_at']],
            'meta' => ['current_page', 'per_page', 'total'],
            'links',
        ]);
    }

    public function test_index_returns_empty_when_no_active_tours_exist(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->count(2)->create(['status' => TourStatus::Draft]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }

    public function test_index_filters_by_category_difficulty_and_search(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $hiking = Category::factory()->create(['slug' => 'hiking', 'name' => 'Hiking']);
        $diving = Category::factory()->create(['slug' => 'diving', 'name' => 'Diving']);

        Tour::factory()->active()->create([
            'name' => 'Cocora Hike',
            'category_id' => $hiking->id,
            'difficulty' => 'moderate',
            'base_price' => '120000.00',
        ]);
        Tour::factory()->active()->create([
            'name' => 'Tayrona Dive',
            'category_id' => $diving->id,
            'difficulty' => 'hard',
            'base_price' => '500000.00',
        ]);

        $byCategory = $this->getJson('http://demo.montree.test/api/v1/tours?category=hiking');
        $byCategory->assertOk();
        $byCategory->assertJsonCount(1, 'data');
        $byCategory->assertJsonPath('data.0.name', 'Cocora Hike');

        $byDifficulty = $this->getJson('http://demo.montree.test/api/v1/tours?difficulty=hard');
        $byDifficulty->assertJsonCount(1, 'data');
        $byDifficulty->assertJsonPath('data.0.name', 'Tayrona Dive');

        $bySearch = $this->getJson('http://demo.montree.test/api/v1/tours?search=cocora');
        $bySearch->assertJsonCount(1, 'data');
        $bySearch->assertJsonPath('data.0.name', 'Cocora Hike');

        $byPrice = $this->getJson('http://demo.montree.test/api/v1/tours?price_min=200000&price_max=600000');
        $byPrice->assertJsonCount(1, 'data');
        $byPrice->assertJsonPath('data.0.name', 'Tayrona Dive');
    }

    public function test_search_matches_category_name_and_slug(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $adventure = Category::factory()->create(['name' => 'Aventura', 'slug' => 'aventura']);
        $culture = Category::factory()->create(['name' => 'Cultura', 'slug' => 'cultura']);

        Tour::factory()->active()->create([
            'name' => 'Salto del Tequendama',
            'short_description' => 'Caminata guiada',
            'description' => 'Recorrido tranquilo.',
            'category_id' => $adventure->id,
        ]);
        Tour::factory()->active()->create([
            'name' => 'Museo del Oro',
            'short_description' => 'Visita guiada',
            'description' => 'Historia precolombina.',
            'category_id' => $culture->id,
        ]);

        $byCategoryName = $this->getJson('http://demo.montree.test/api/v1/tours?search=aventura');

        $byCategoryName->assertOk();
        $byCategoryName->assertJsonCount(1, 'data');
        $byCategoryName->assertJsonPath('data.0.name', 'Salto del Tequendama');

        $byCategorySlug = $this->getJson('http://demo.montree.test/api/v1/tours?search=cultura');

        $byCategorySlug->assertOk();
        $byCategorySlug->assertJsonCount(1, 'data');
        $byCategorySlug->assertJsonPath('data.0.name', 'Museo del Oro');
    }

    public function test_sort_next_date_asc_orders_by_soonest_future_open_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $later = Tour::factory()->active()->create(['name' => 'Later Tour']);
        TourDate::factory()->for($later)->create([
            'starts_at' => now()->addDays(20),
            'status' => TourDateStatus::Open,
        ]);

        $sooner = Tour::factory()->active()->create(['name' => 'Sooner Tour']);
        TourDate::factory()->for($sooner)->create([
            'starts_at' => now()->addDays(2),
            'status' => TourDateStatus::Open,
        ]);

        Tour::factory()->active()->create(['name' => 'No Dates Tour']);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours?sort=next_date_asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Sooner Tour');
        $response->assertJsonPath('data.1.name', 'Later Tour');
        $response->assertJsonPath('data.2.name', 'No Dates Tour');
        $response->assertJsonPath('data.2.has_future_dates', false);
    }

    public function test_index_rejects_invalid_sort_with_422(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $response = $this->getJson('http://demo.montree.test/api/v1/tours?sort=cheapest');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sort']);
    }

    public function test_per_page_is_capped_at_48(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->active()->count(2)->create();

        $response = $this->getJson('http://demo.montree.test/api/v1/tours?per_page=200');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 48);
    }

    public function test_is_favorite_true_for_authenticated_users_favorite_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $user = User::factory()->create();
        $favoriteTour = Tour::factory()->active()->create(['name' => 'Loved Tour']);
        Tour::factory()->active()->create(['name' => 'Other Tour']);
        Favorite::factory()->create(['user_id' => $user->id, 'tour_id' => $favoriteTour->id]);

        $response = $this->actingAs($user)->getJson('http://demo.montree.test/api/v1/tours?sort=newest');

        $response->assertOk();
        $favoriteEntries = collect($response->json('data'))->keyBy('name');
        $this->assertTrue($favoriteEntries['Loved Tour']['is_favorite']);
        $this->assertFalse($favoriteEntries['Other Tour']['is_favorite']);
    }

    public function test_tenant_isolation_prevents_seeing_other_tenant_tours(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        Tour::factory()->active()->create(['name' => 'Tour A']);

        $tenantB->makeCurrent();
        Tour::factory()->active()->create(['name' => 'Tour B']);

        Tenant::forgetCurrent();

        $response = $this->getJson('http://alpha.montree.test/api/v1/tours');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Tour A');
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
