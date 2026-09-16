<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Enums\TourStopKind;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El detalle público muestra la ruta de la salida elegida, no la del producto
 * (spec §G). Sin ruta, la salida lo dice y la pantalla cae a las paradas del
 * producto.
 */
final class PublicTourDepartureRouteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_a_departure_with_route_exposes_its_stops_in_order(): void
    {
        $tour = Tour::factory()->create(['slug' => 'cocora', 'status' => TourStatus::Active]);
        $route = Route::factory()->create([
            'name' => 'Sendero alto',
            'distance_km' => '12.50',
            'duration_hours' => '4.0',
        ]);
        RouteStop::factory()->for($route)->create([
            'position' => 2,
            'name' => 'Mirador',
            'kind' => TourStopKind::Site,
            'latitude' => '4.6350000',
            'longitude' => '-75.4900000',
        ]);
        RouteStop::factory()->for($route)->create([
            'position' => 1,
            'name' => 'Plaza principal',
            'kind' => TourStopKind::Pickup,
            'latitude' => '4.6300000',
            'longitude' => '-75.4800000',
        ]);
        $guide = User::factory()->create(['name' => 'Ana Guía']);
        TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'route_id' => $route->id,
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
        ]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/cocora');

        $response->assertOk();
        $response->assertJsonPath('data.future_dates.0.route.name', 'Sendero alto');
        $response->assertJsonPath('data.future_dates.0.route.distance_km', '12.50');
        $response->assertJsonPath('data.future_dates.0.route.stops.0.name', 'Plaza principal');
        $response->assertJsonPath('data.future_dates.0.route.stops.1.name', 'Mirador');
        $response->assertJsonPath('data.future_dates.0.route.stops.0.latitude', '4.6300000');
        $response->assertJsonPath('data.future_dates.0.guide.name', 'Ana Guía');
    }

    public function test_a_departure_without_route_exposes_a_null_route(): void
    {
        $tour = Tour::factory()->create(['slug' => 'sin-ruta', 'status' => TourStatus::Active]);
        TourDate::factory()->for($tour)->create([
            'route_id' => null,
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/sin-ruta')
            ->assertOk()
            ->assertJsonPath('data.future_dates.0.route', null);
    }

    public function test_route_stops_without_coordinates_travel_as_null(): void
    {
        $tour = Tour::factory()->create(['slug' => 'sin-coordenadas', 'status' => TourStatus::Active]);
        $route = Route::factory()->create();
        RouteStop::factory()->for($route)->create([
            'position' => 1,
            'latitude' => null,
            'longitude' => null,
        ]);
        TourDate::factory()->for($tour)->create([
            'route_id' => $route->id,
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/sin-coordenadas')
            ->assertOk()
            ->assertJsonPath('data.future_dates.0.route.stops.0.latitude', null)
            ->assertJsonPath('data.future_dates.0.route.stops.0.longitude', null);
    }

    public function test_the_detail_of_another_tenant_is_not_reachable(): void
    {
        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        Tour::factory()->create(['slug' => 'ajeno', 'status' => TourStatus::Active]);
        $this->tenant->makeCurrent();

        $this->getJson('http://demo.montree.test/api/v1/tours/ajeno')->assertNotFound();
    }
}
