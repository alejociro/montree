<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateRouteTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_updating_a_route_rewrites_its_stops_in_order(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $route->stops()->createMany([
            ['position' => 1, 'name' => 'Vieja', 'kind' => TourStopKind::Pickup->value],
            ['position' => 2, 'name' => 'Otra vieja', 'kind' => TourStopKind::Site->value],
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/routes/{$route->id}",
            [
                'name' => $route->name,
                'stops' => [
                    ['name' => 'Nueva salida', 'kind' => TourStopKind::Pickup->value],
                ],
            ],
        );

        $response->assertSessionHas('success');
        $this->assertSame(1, $route->stops()->count());
        $this->assertSame('Nueva salida', $route->stops()->firstOrFail()->name);
    }

    /**
     * Parchear un campo suelto no puede borrar las listas: quien corrige el
     * teléfono desde otra pantalla no está pidiendo perder las paradas.
     */
    public function test_a_partial_update_keeps_the_stops_untouched(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $route->stops()->create([
            'position' => 1,
            'name' => 'Plaza',
            'kind' => TourStopKind::Pickup->value,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/routes/{$route->id}",
            ['name' => 'Otro nombre'],
        )->assertSessionHasNoErrors();

        $this->assertSame(1, $route->stops()->count());
        $this->assertSame('Otro nombre', $route->fresh()?->name);
    }

    public function test_updating_a_route_without_a_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->put($this->host($tenant)."/admin/routes/{$route->id}", ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_updating_a_route_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $routeB = Route::factory()->create(['name' => 'De Bravo']);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->put($this->host($tenantA)."/admin/routes/{$routeB->id}", ['name' => 'Hackeada'])
            ->assertNotFound();

        $this->assertSame('De Bravo', $routeB->fresh()?->name);
    }
}
