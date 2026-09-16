<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Una ruta en uso no se borra: se nombra quién la usa (spec §G).
 */
final class RouteInUseDeletionTest extends TestCase
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

    public function test_an_unused_route_is_deleted(): void
    {
        $route = Route::factory()->create();

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_a_route_used_by_a_product_cannot_be_deleted_and_the_product_is_named(): void
    {
        $route = Route::factory()->create();
        $tour = Tour::factory()->create(['name' => 'Nevado del Ruiz']);
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);

        $response = $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHasErrors('route');

        $this->assertStringContainsString(
            'Nevado del Ruiz',
            (string) session('errors')?->first('route'),
        );
        $this->assertDatabaseHas('routes', ['id' => $route->id]);
        $response->assertRedirect();
    }

    public function test_a_route_used_by_a_departure_cannot_be_deleted(): void
    {
        $route = Route::factory()->create();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor($this->tenant)->id,
            'route_id' => $route->id,
        ]);

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHasErrors('route');

        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }

    public function test_a_route_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        $foreignRoute = Route::factory()->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$foreignRoute->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('routes', ['id' => $foreignRoute->id]);
    }

    public function test_an_operator_without_the_logistics_permission_cannot_delete(): void
    {
        $route = Route::factory()->create();
        $guide = $this->guideFor($this->tenant);

        $this->actingAs($guide)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }
}
