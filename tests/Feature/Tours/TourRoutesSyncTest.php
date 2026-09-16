<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rutas del catálogo de logística asociadas a un producto (spec §G).
 */
final class TourRoutesSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($this->tenant)->create();
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_updating_a_tour_associates_routes_and_marks_the_default(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();
        $first = Route::factory()->create();
        $second = Route::factory()->create();

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, [
                'routes' => [
                    ['id' => $first->id, 'is_default' => false],
                    ['id' => $second->id, 'is_default' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('route_tour', [
            'tour_id' => $tour->id,
            'route_id' => $first->id,
            'is_default' => false,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('route_tour', [
            'tour_id' => $tour->id,
            'route_id' => $second->id,
            'is_default' => true,
            'position' => 2,
        ]);
    }

    public function test_changing_the_default_route_clears_the_previous_one(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();
        $first = Route::factory()->create();
        $second = Route::factory()->create();
        $tour->routes()->attach([
            $first->id => ['is_default' => true, 'position' => 1],
            $second->id => ['is_default' => false, 'position' => 2],
        ]);

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, [
                'routes' => [
                    ['id' => $first->id, 'is_default' => false],
                    ['id' => $second->id, 'is_default' => true],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($second->id, $tour->fresh()?->defaultRoute()?->id);
    }

    public function test_two_default_routes_are_rejected(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();
        $first = Route::factory()->create();
        $second = Route::factory()->create();

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, [
                'routes' => [
                    ['id' => $first->id, 'is_default' => true],
                    ['id' => $second->id, 'is_default' => true],
                ],
            ])
            ->assertSessionHasErrors('routes');

        $this->assertDatabaseCount('route_tour', 0);
    }

    public function test_a_route_from_another_tenant_is_rejected(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();

        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($other)->create();
        $other->makeCurrent();
        $foreignRoute = Route::factory()->create();
        $this->tenant->makeCurrent();

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, [
                'routes' => [['id' => $foreignRoute->id, 'is_default' => true]],
            ])
            ->assertSessionHasErrors('routes.0.id');

        $this->assertDatabaseCount('route_tour', 0);
    }

    public function test_an_empty_routes_array_detaches_every_route(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, ['routes' => []])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('route_tour', 0);
    }

    public function test_omitting_routes_leaves_the_existing_ones_untouched(): void
    {
        $admin = $this->admin();
        $tour = Tour::factory()->create();
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);

        $this->actingAs($admin)
            ->put('http://demo.montree.test/admin/tours/'.$tour->id, ['name' => 'Otro nombre'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('route_tour', ['tour_id' => $tour->id, 'route_id' => $route->id]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        Role::findOrCreate(UserRole::Admin->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }
}
