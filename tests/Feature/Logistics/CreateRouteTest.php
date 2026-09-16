<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CreateRouteTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_creates_a_route(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/routes',
            ['name' => 'Ruta Cocora', 'distance_km' => '8.5', 'duration_hours' => '5'],
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('routes', ['name' => 'Ruta Cocora', 'tenant_id' => $tenant->id]);
    }

    public function test_it_stores_a_full_route_with_its_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/routes',
            [
                'name' => 'Ruta Cascadas',
                'kind' => RouteKind::Hiking->value,
                'difficulty' => TourDifficulty::Moderate->value,
                'start_point' => 'Plaza de Bolívar, Salento',
                'start_latitude' => 4.6376,
                'start_longitude' => -75.5706,
                'city' => 'Salento',
                'state' => 'Quindío',
                'distance_km' => 11.4,
                'duration_hours' => 6,
                'max_altitude_m' => 2860,
                'elevation_gain_m' => 640,
                'group_capacity' => 20,
                'seasons' => [RouteSeason::AllYear->value],
                'required_gear' => ['Calzado de trekking', 'Impermeable'],
                'emergency_contact' => 'Bomberos Salento',
                'stops' => [
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Pickup->value, 'time_label' => '8:00 a. m.'],
                    ['name' => 'Mirador', 'kind' => TourStopKind::Site->value, 'time_label' => null],
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Drop->value, 'time_label' => '5:00 p. m.'],
                ],
            ],
        );

        $response->assertSessionHas('success');

        $route = Route::query()->firstOrFail();
        $this->assertSame(RouteKind::Hiking, $route->kind);
        $this->assertSame([RouteSeason::AllYear->value], $route->seasons);
        $this->assertSame('Impermeable', $route->required_gear[1]);
        $this->assertSame(3, $route->stops()->count());
        $lastStop = $route->stops()->orderBy('position')->get()->last();
        $this->assertSame(3, $lastStop?->position);
        $this->assertSame(TourStopKind::Drop, $lastStop?->kind);
    }

    public function test_creating_a_route_without_a_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant).'/admin/routes', ['distance_km' => '8.5'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_sales_member_cannot_create_a_route(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->post($this->host($tenant).'/admin/routes', ['name' => 'Ruta Prohibida'])
            ->assertForbidden();
    }

    public function test_the_new_route_belongs_to_the_acting_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $this->actingAs($adminA)
            ->post($this->host($tenantA).'/admin/routes', ['name' => 'Solo de Alpha'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('routes', ['name' => 'Solo de Alpha', 'tenant_id' => $tenantA->id]);
        $this->assertSame(1, Route::query()->withoutGlobalScope(Tenant::SCOPE)->count());
    }
}
