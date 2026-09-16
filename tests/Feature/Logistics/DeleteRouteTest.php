<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteRouteTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_deleting_an_unused_route_succeeds(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/routes/{$route->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    /**
     * El 409 `RESOURCE_IN_USE` se volvió un error de formulario en la clave
     * `route`, con el conteo de salidas que la usan.
     */
    public function test_deleting_a_route_used_by_a_departure_is_blocked(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['route_id' => $route->id]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/routes/{$route->id}");

        $response->assertSessionHasErrors('route');
        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }

    public function test_a_sales_member_cannot_delete_a_route(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->delete($this->host($tenant)."/admin/routes/{$route->id}")
            ->assertForbidden();
    }

    public function test_deleting_a_route_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $routeB = Route::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->delete($this->host($tenantA)."/admin/routes/{$routeB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('routes', ['id' => $routeB->id]);
    }
}
