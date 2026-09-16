<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Un producto tiene como mucho una ruta predeterminada. Sin marca explícita se
 * queda sin ella: la salida nueva arranca entonces «Sin ruta» (spec, edge cases).
 */
final class DefaultRouteTest extends TestCase
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

    public function test_marking_a_route_as_default_unmarks_the_previous_one(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $first = Route::factory()->for($tour)->create(['is_default' => true]);
        $second = Route::factory()->for($tour)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->patch($this->host($tenant)."/admin/routes/{$second->id}/default")
            ->assertSessionHas('success');

        $this->assertTrue($second->fresh()?->is_default);
        $this->assertFalse($first->fresh()?->is_default);
    }

    public function test_a_route_created_without_the_mark_leaves_the_product_without_a_default(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant)."/admin/tours/{$tour->id}/routes", ['name' => 'Ruta sin marca'])
            ->assertSessionHasNoErrors();

        $this->assertNull($tour->fresh()?->defaultRoute());
    }

    public function test_the_default_of_a_product_never_touches_another_product(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $mine = Route::factory()->for(Tour::factory())->create(['is_default' => true]);
        $theirs = Route::factory()->for(Tour::factory())->create(['is_default' => true]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->patch($this->host($tenant)."/admin/routes/{$mine->id}/default")
            ->assertSessionHasNoErrors();

        $this->assertTrue($theirs->fresh()?->is_default);
    }

    public function test_a_sales_member_cannot_change_the_default(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->for(Tour::factory())->create();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->patch($this->host($tenant)."/admin/routes/{$route->id}/default")
            ->assertForbidden();
    }
}
