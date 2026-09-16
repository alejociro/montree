<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Las rutas son del producto: se crean y se editan desde su ficha (spec §I).
 */
final class TourRouteCrudTest extends TestCase
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

    public function test_an_admin_creates_a_route_of_the_product_with_its_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/routes",
            [
                'name' => 'Ruta Cascadas',
                'kind' => RouteKind::Hiking->value,
                'difficulty' => TourDifficulty::Moderate->value,
                'distance_km' => 11.4,
                'duration_hours' => 6,
                'seasons' => [RouteSeason::AllYear->value],
                'stops' => [
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Pickup->value, 'latitude' => 4.6376, 'longitude' => -75.5706],
                    ['name' => 'Mirador', 'kind' => TourStopKind::Site->value],
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Drop->value],
                ],
            ],
        );

        $response->assertSessionHas('success');

        $route = Route::query()->firstOrFail();
        $this->assertSame($tour->id, $route->tour_id);
        $this->assertSame(3, $route->stops()->count());
        $this->assertSame('4.6376000', $route->stops()->firstOrFail()->latitude);
    }

    public function test_creating_a_route_without_a_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant)."/admin/tours/{$tour->id}/routes", ['distance_km' => '8.5'])
            ->assertSessionHasErrors('name');
    }

    public function test_updating_a_route_rewrites_its_stops_in_order(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create();
        $route->stops()->createMany([
            ['position' => 1, 'name' => 'Vieja', 'kind' => TourStopKind::Pickup->value],
            ['position' => 2, 'name' => 'Otra vieja', 'kind' => TourStopKind::Site->value],
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/routes/{$route->id}",
            [
                'name' => $route->name,
                'stops' => [['name' => 'Nueva salida', 'kind' => TourStopKind::Pickup->value]],
            ],
        )->assertSessionHas('success');

        $this->assertSame(1, $route->stops()->count());
        $this->assertSame('Nueva salida', $route->stops()->firstOrFail()->name);
    }

    /**
     * Parchear un campo suelto no puede borrar las paradas: quien corrige el
     * nombre desde otra pantalla no está pidiendo perder el recorrido.
     */
    public function test_a_partial_update_keeps_the_stops_untouched(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->for(Tour::factory())->create();
        $route->stops()->create(['position' => 1, 'name' => 'Plaza', 'kind' => TourStopKind::Pickup->value]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->put($this->host($tenant)."/admin/routes/{$route->id}", ['name' => 'Otro nombre'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $route->stops()->count());
        $this->assertSame('Otro nombre', $route->fresh()?->name);
    }

    public function test_a_sales_member_cannot_create_a_route(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->post($this->host($tenant)."/admin/tours/{$tour->id}/routes", ['name' => 'Ruta prohibida'])
            ->assertForbidden();
    }

    public function test_a_route_of_another_tenant_is_not_reachable(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $routeB = Route::factory()->for(Tour::factory())->create(['name' => 'De Bravo']);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->put($this->host($tenantA)."/admin/routes/{$routeB->id}", ['name' => 'Hackeada'])
            ->assertNotFound();

        $this->assertSame('De Bravo', $routeB->fresh()?->name);
    }

    public function test_the_edit_page_carries_the_routes_of_the_product_only(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        $mine = Route::factory()->for($tour)->create(['name' => 'Mía']);
        Route::factory()->for($other)->create(['name' => 'De otro producto']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->get($this->host($tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('tour.routes.0.id', $mine->id)
                ->count('tour.routes', 1));
    }
}
