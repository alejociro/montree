<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Desactivar una categoría la retira de la vitrina —home, catálogo y filtros—
 * pero no la arranca de los productos que ya la usan: el formulario de edición
 * la sigue ofreciendo para que guardar no la borre sin que nadie lo pida.
 */
final class InactiveCategoryVisibilityTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_an_inactive_category_disappears_from_the_catalog_filters(): void
    {
        $active = Category::factory()->create(['name' => 'Activa']);
        $inactive = Category::factory()->inactive()->create(['name' => 'Inactiva']);
        Tour::factory()->create(['category_id' => $active->id, 'status' => TourStatus::Active]);
        Tour::factory()->create(['category_id' => $inactive->id, 'status' => TourStatus::Active]);

        $response = $this->partialReload('/tours', 'Catalog', ['categories']);

        $response->assertOk();
        $this->assertCount(1, $response->json('props.categories'));
        $response->assertJsonPath('props.categories.0.name', 'Activa');
    }

    public function test_an_inactive_category_disappears_from_the_home_page(): void
    {
        $inactive = Category::factory()->inactive()->create(['name' => 'Inactiva']);
        Tour::factory()->create(['category_id' => $inactive->id, 'status' => TourStatus::Active]);

        $response = $this->partialReload('/', 'Home', ['categories']);

        $response->assertOk();
        $this->assertCount(0, $response->json('props.categories'));
    }

    public function test_the_tour_keeps_its_category_and_the_edit_form_still_offers_it(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $inactive = Category::factory()->inactive()->create(['name' => 'Inactiva']);
        Category::factory()->create(['name' => 'Activa']);
        $tour = Tour::factory()->create(['category_id' => $inactive->id]);

        $response = $this->actingAs($admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tour.category_id', $inactive->id)
            ->has('categories', 2)
        );
    }

    public function test_the_create_form_only_offers_active_categories(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        Category::factory()->inactive()->create(['name' => 'Inactiva']);
        Category::factory()->create(['name' => 'Activa']);

        $response = $this->actingAs($admin)->get($this->host($this->tenant).'/admin/tours/create');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Activa')
        );
    }

    /**
     * Los catálogos públicos viajan como props diferidas: se piden aparte.
     *
     * @param  array<int, string>  $only
     */
    private function partialReload(string $path, string $component, array $only): TestResponse
    {
        return $this->get($this->host($this->tenant).$path, [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => implode(',', $only),
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        ]);
    }
}
