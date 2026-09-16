<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Las pantallas de alta y edición de producto servidas por rutas web.
 */
final class TourCreateUpdatePageTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();
        $this->admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    /**
     * El alta no ofrece rutas: se crean desde la ficha del producto, que todavía
     * no existe (spec §I).
     */
    public function test_the_create_page_announces_that_routes_come_after_saving(): void
    {
        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/tours/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tour/Create')
                ->missing('availableRoutes')
            );
    }

    public function test_storing_a_tour_redirects_to_its_edition(): void
    {
        $response = $this->actingAs($this->admin)
            ->post($this->host($this->tenant).'/admin/tours', $this->payload());

        $response->assertSessionHasNoErrors();
        $tour = Tour::query()->firstOrFail();
        $response->assertRedirect($this->host($this->tenant)."/admin/tours/{$tour->id}/edit");
    }

    public function test_storing_a_tour_without_the_required_stops_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post($this->host($this->tenant).'/admin/tours', $this->payload(['stops' => []]))
            ->assertSessionHasErrors('stops');

        $this->assertDatabaseCount('tours', 0);
    }

    public function test_the_edit_page_carries_the_routes_of_the_product(): void
    {
        $tour = Tour::factory()->create();
        Route::factory()->for($tour)->create(['name' => 'Ruta larga', 'is_default' => true]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tour/Edit')
                ->where('tour.routes.0.name', 'Ruta larga')
                ->where('tour.routes.0.is_default', true)
                ->has('departures')
                ->has('departureOptions')
            );
    }

    public function test_the_show_page_lists_the_routes_of_the_product(): void
    {
        $tour = Tour::factory()->create();
        Route::factory()->for($tour)->create(['name' => 'Ruta corta']);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tour/Show')
                ->where('tour.routes.0.name', 'Ruta corta')
                ->where('tour.routes.0.stops_count', 0)
            );
    }

    public function test_an_operator_without_the_create_permission_cannot_store_a_tour(): void
    {
        $guide = $this->guideFor($this->tenant);

        $this->actingAs($guide)
            ->post($this->host($this->tenant).'/admin/tours', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('tours', 0);
    }

    public function test_the_edit_page_never_carries_routes_of_another_tenant(): void
    {
        $tour = Tour::factory()->create();
        Route::factory()->for($tour)->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        Route::factory()->count(3)->for(Tour::factory())->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tour.routes', 1));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Caminata al valle',
            'description' => 'Una caminata de un día por el valle.',
            'base_price' => '120000',
            'duration_hours' => 8,
            'difficulty' => 'moderate',
            'default_capacity' => 12,
            'meeting_point' => 'Plaza de Bolívar',
            'meeting_latitude' => 4.535,
            'meeting_longitude' => -75.681,
            'stops' => [
                ['kind' => TourStopKind::Pickup->value, 'name' => 'Plaza', 'latitude' => 4.535, 'longitude' => -75.681],
                ['kind' => TourStopKind::Site->value, 'name' => 'Mirador', 'latitude' => 4.635, 'longitude' => -75.49],
                ['kind' => TourStopKind::Drop->value, 'name' => 'Terminal', 'latitude' => 4.525, 'longitude' => -75.68],
            ],
        ], $overrides);
    }
}
