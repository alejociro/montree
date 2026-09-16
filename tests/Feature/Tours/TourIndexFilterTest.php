<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Listado del panel: qué productos viajan a la página y con qué filtros.
 */
final class TourIndexFilterTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_lists_the_tours_of_the_current_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->count(3)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->get($this->host($tenant).'/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Tour/Index', false)
            ->has('tours.data', 3)
            ->has('tours.meta')
            ->has('tours.links')
            ->has('tours.data.0', fn (AssertableInertia $tour) => $tour
                ->where('status', TourStatus::Draft->value)
                ->hasAll(['id', 'slug', 'name', 'base_price'])
                ->etc())
        );
    }

    public function test_index_resolves_cover_image_url_for_external_and_stored_paths(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $externalTour = Tour::factory()->create(['name' => 'Externa']);
        TourImage::factory()->cover()->create([
            'tour_id' => $externalTour->id,
            'path' => 'https://picsum.photos/seed/demo/1200/800',
        ]);
        $storedTour = Tour::factory()->create(['name' => 'Subida']);
        TourImage::factory()->cover()->create([
            'tour_id' => $storedTour->id,
            'path' => 'tours/cover.jpg',
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->get($this->host($tenant).'/admin/tours?sort=name&direction=asc');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tours.data.0.cover_image_url', 'https://picsum.photos/seed/demo/1200/800')
            ->where('tours.data.1.cover_image_url', fn (?string $url) => str_ends_with((string) $url, '/storage/tours/cover.jpg'))
        );
    }

    public function test_index_filters_by_status_search_and_category(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $category = Category::factory()->create(['name' => 'Senderismo', 'slug' => 'senderismo']);
        Tour::factory()->create(['name' => 'Camino de Cocora', 'status' => TourStatus::Active, 'category_id' => $category->id]);
        Tour::factory()->create(['name' => 'Buceo Tayrona', 'status' => TourStatus::Draft]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $host = $this->host($tenant);

        $byStatus = $this->actingAs($admin)->get($host.'/admin/tours?status=active');
        $byStatus->assertOk();
        $byStatus->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tours.data', 1)
            ->where('tours.data.0.name', 'Camino de Cocora')
            ->where('filters.status', 'active')
        );

        $bySearch = $this->actingAs($admin)->get($host.'/admin/tours?search=Buceo');
        $bySearch->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tours.data', 1)
            ->where('tours.data.0.name', 'Buceo Tayrona')
            ->where('filters.search', 'Buceo')
        );

        $byCategory = $this->actingAs($admin)->get($host."/admin/tours?category_id={$category->id}");
        $byCategory->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tours.data', 1)
            ->where('filters.category_id', $category->id)
        );
    }

    /**
     * El listado ya no es JSON: un `sort` desconocido no puede degradarse en
     * silencio, tiene que rebotar como error de validación.
     */
    public function test_index_rejects_an_unknown_sort(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->from($this->host($tenant).'/admin/tours')
            ->get($this->host($tenant).'/admin/tours?sort=drop table&direction=desc');

        $response->assertSessionHasErrors('sort');
    }

    public function test_index_paginates_after_nine_tours(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->count(11)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $first = $this->actingAs($admin)->get($this->host($tenant).'/admin/tours');
        $first->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tours.data', 9)
            ->where('tours.meta.total', 11)
            ->where('tours.meta.last_page', 2)
        );

        $second = $this->actingAs($admin)->get($this->host($tenant).'/admin/tours?page=2');
        $second->assertInertia(fn (AssertableInertia $page) => $page->has('tours.data', 2));
    }

    public function test_index_hides_the_tours_of_another_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        Tour::factory()->create(['name' => 'Tour A']);
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        Tour::factory()->create(['name' => 'Tour B']);

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->get($this->host($tenantA).'/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tours.data', 1)
            ->where('tours.data.0.name', 'Tour A')
        );
    }
}
