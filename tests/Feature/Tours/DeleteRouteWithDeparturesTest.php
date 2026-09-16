<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TourDateStatus;
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
 * Una ruta que todavía se va a operar no se borra; la que solo respalda salidas
 * pasadas o canceladas sí, y esas salidas quedan sin ruta (spec §I).
 */
final class DeleteRouteWithDeparturesTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

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
        $route = Route::factory()->for(Tour::factory())->create();

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_a_route_of_a_future_open_departure_cannot_be_deleted(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create();
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor($this->tenant)->id,
            'route_id' => $route->id,
            'starts_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHasErrors([
                'route' => sprintf(
                    'No se puede eliminar: la ruta está en uso por 1 salida (%s).',
                    $departure->starts_at->format('d/m/Y H:i'),
                ),
            ]);

        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }

    /**
     * Criterio I: el rechazo nombra las salidas. Con más de tres se listan las
     * tres primeras y el resto se resume, para que el mensaje siga siendo legible.
     */
    public function test_the_rejection_lists_the_first_three_departures_and_counts_the_rest(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create();
        $guide = $this->guideFor($this->tenant);

        $departures = collect(range(1, 4))->map(fn (int $week) => TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'route_id' => $route->id,
            'starts_at' => now()->addWeeks($week),
        ]));

        $expected = sprintf(
            'No se puede eliminar: la ruta está en uso por 4 salidas (%s y 1 más).',
            $departures->take(3)
                ->map(fn (TourDate $departure): string => $departure->starts_at->format('d/m/Y H:i'))
                ->implode(', '),
        );

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHasErrors(['route' => $expected]);

        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }

    public function test_a_past_departure_releases_the_route_and_keeps_its_own_record(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create();
        $past = TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor($this->tenant)->id,
            'route_id' => $route->id,
            'starts_at' => now()->subMonth(),
        ]);

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
        $this->assertNull($past->fresh()?->route_id);
    }

    public function test_a_cancelled_future_departure_does_not_block_the_deletion(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create();
        TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor($this->tenant)->id,
            'route_id' => $route->id,
            'starts_at' => now()->addWeek(),
            'status' => TourDateStatus::Cancelled,
        ]);

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$route->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_a_route_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        $foreign = Route::factory()->for(Tour::factory())->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->delete($this->host($this->tenant)."/admin/routes/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('routes', ['id' => $foreign->id]);
    }
}
