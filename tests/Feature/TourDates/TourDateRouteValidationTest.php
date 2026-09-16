<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

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
 * Una salida solo puede operar una de las rutas de su producto (spec §G).
 */
final class TourDateRouteValidationTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $guide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();
        $this->admin = $this->memberFor($this->tenant, UserRole::Admin);
        $this->guide = $this->guideFor($this->tenant);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_route_of_the_product_is_accepted(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create(['is_default' => true]);

        $this->actingAs($this->admin)
            ->post($this->host($this->tenant)."/admin/tours/{$tour->id}/dates", $this->payload(['route_id' => $route->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tour_dates', ['tour_id' => $tour->id, 'route_id' => $route->id]);
    }

    public function test_a_route_of_another_product_is_rejected(): void
    {
        $tour = Tour::factory()->create();
        $strayRoute = Route::factory()->for(Tour::factory())->create();

        $this->actingAs($this->admin)
            ->post($this->host($this->tenant)."/admin/tours/{$tour->id}/dates", $this->payload(['route_id' => $strayRoute->id]))
            ->assertSessionHasErrors('route_id');

        $this->assertDatabaseCount('tour_dates', 0);
    }

    public function test_a_departure_without_route_is_accepted(): void
    {
        $tour = Tour::factory()->create();

        $this->actingAs($this->admin)
            ->post($this->host($this->tenant)."/admin/tours/{$tour->id}/dates", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tour_dates', ['tour_id' => $tour->id, 'route_id' => null]);
    }

    public function test_editing_swaps_the_route_between_the_ones_of_the_product(): void
    {
        $tour = Tour::factory()->create();
        $first = Route::factory()->for($tour)->create(['is_default' => true]);
        $second = Route::factory()->for($tour)->create();
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guide->id,
            'route_id' => $first->id,
        ]);

        $this->actingAs($this->admin)
            ->put($this->host($this->tenant)."/admin/tour-dates/{$departure->id}", ['route_id' => $second->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($second->id, $departure->fresh()?->route_id);
    }

    public function test_a_route_of_another_tenant_is_rejected(): void
    {
        $tour = Tour::factory()->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        $foreignRoute = Route::factory()->for(Tour::factory())->create(['is_default' => true]);
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->post($this->host($this->tenant)."/admin/tours/{$tour->id}/dates", $this->payload(['route_id' => $foreignRoute->id]))
            ->assertSessionHasErrors('route_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'starts_at' => now()->addDays(10)->toIso8601String(),
            'capacity' => 8,
            'guide_id' => $this->guide->id,
        ], $overrides);
    }
}
