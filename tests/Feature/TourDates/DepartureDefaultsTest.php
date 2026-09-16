<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

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
 * Lo que una salida nueva hereda del producto y de la agencia (spec §G).
 */
final class DepartureDefaultsTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->configuration()->update(['min_partial_payment_pct' => 45]);
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

    public function test_the_edit_page_inherits_guide_capacity_route_price_and_agency_percentage(): void
    {
        $guide = $this->guideFor($this->tenant);
        $tour = Tour::factory()->create([
            'default_guide_id' => $guide->id,
            'default_capacity' => 17,
            'base_price' => '250000.00',
            'currency' => 'COP',
        ]);
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departureDefaults.guide_id', $guide->id)
                ->where('departureDefaults.capacity', 17)
                ->where('departureDefaults.route_id', $route->id)
                ->where('departureDefaults.base_price', '250000.00')
                ->where('departureDefaults.min_payment_pct', 45)
                ->where('departureDefaults.currency', 'COP')
            );
    }

    public function test_a_product_without_routes_inherits_no_route(): void
    {
        $tour = Tour::factory()->create();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departureDefaults.route_id', null)
                ->where('tour.routes', [])
            );
    }

    /**
     * Edge case de la spec: quitar la ruta predeterminada deja la salida nueva
     * sin ruta preseleccionada en vez de heredar otra por descarte.
     */
    public function test_routes_without_an_explicit_default_inherit_no_route(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => false, 'position' => 1]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departureDefaults.route_id', null)
                ->has('tour.routes', 1)
            );
    }

    public function test_the_departure_board_carries_the_defaults_of_every_product(): void
    {
        $tour = Tour::factory()->create(['default_capacity' => 9]);
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tours', 1)
                ->where('tours.0.departure_defaults.capacity', 9)
                ->where('tours.0.departure_defaults.route_id', $route->id)
                ->where('tours.0.routes.0.is_default', true)
            );
    }

    public function test_the_defaults_never_cross_tenants(): void
    {
        $tour = Tour::factory()->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        Tour::factory()->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tours', 1)
                ->where('tours.0.id', $tour->id)
            );
    }
}
