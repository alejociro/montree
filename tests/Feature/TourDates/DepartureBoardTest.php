<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\TourDateDisplayStatus;
use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Tablero de salidas: bandejas, buscador, filtros y cifras de cabecera de
 * `Admin/Departures/Index`.
 */
final class DepartureBoardTest extends TestCase
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

    public function test_scope_splits_upcoming_past_and_disabled(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();

        $upcoming = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(6),
            'status' => TourDateStatus::Open,
        ]);
        $past = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subDays(4)->addHours(6),
            'status' => TourDateStatus::Open,
        ]);
        $disabled = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(6),
            'status' => TourDateStatus::Cancelled,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->assertSingle($tenant, $admin, 'scope=upcoming', $upcoming->id);
        $this->assertSingle($tenant, $admin, 'scope=past', $past->id);
        $this->assertSingle($tenant, $admin, 'scope=disabled', $disabled->id);

        $all = $this->actingAs($admin)->get($this->url($tenant, 'scope=all'));
        $all->assertOk();
        $all->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Departures/Index', false)
            ->has('departures.data', 3)
            ->where('counts.upcoming', 1)
            ->where('counts.past', 1)
            ->where('counts.disabled', 1)
            ->where('counts.all', 3)
        );
    }

    public function test_search_matches_tour_name_and_derived_code(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $cocora = Tour::factory()->create(['name' => 'Cocora Trek']);
        $andes = Tour::factory()->create(['name' => 'Andes Ride']);

        $target = TourDate::factory()->for($cocora)->create([
            'starts_at' => now()->addDays(3)->setTime(7, 15),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($andes)->create([
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->assertSingle($tenant, $admin, 'scope=all&search=Cocora', $target->id);
        $this->assertSingle($tenant, $admin, 'scope=all&search='.$target->code(), $target->id);
    }

    public function test_stats_ignore_the_active_tray_and_search(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['name' => 'Cocora Trek']);

        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
            'booked_count' => 4,
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(8),
            'capacity' => 6,
            'booked_count' => 6,
            'status' => TourDateStatus::Full,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        // La bandeja «inhabilitadas» no devuelve ninguna fila; los KPIs siguen
        // describiendo la operación entera.
        $response = $this->actingAs($admin)->get($this->url($tenant, 'scope=disabled'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 0)
            ->where('stats.active', 2)
            ->where('stats.seats_left', 6)
            ->where('stats.travellers', 10)
        );
    }

    public function test_index_lists_departures_across_products_with_tour_embedded(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tourA = Tour::factory()->create(['name' => 'Cocora Trek']);
        $tourB = Tour::factory()->create(['name' => 'Andes Ride']);
        TourDate::factory()->for($tourA)->create(['starts_at' => now()->addDays(2)]);
        $latest = TourDate::factory()->for($tourB)->create(['starts_at' => now()->addDays(9)]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->get($this->url($tenant));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 2)
            ->where('departures.data.0.id', $latest->id)
            ->where('departures.data.0.tour.name', 'Andes Ride')
            ->where('departures.meta.per_page', 15)
        );
    }

    public function test_index_is_forbidden_for_a_guide(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $guide = $this->guideFor($tenant);

        $this->actingAs($guide)->get($this->url($tenant))->assertForbidden();
    }

    public function test_index_rejects_an_invalid_status_filter(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->from($this->url($tenant))
            ->get($this->url($tenant, 'status=invalido'))
            ->assertSessionHasErrors('status');
    }

    public function test_index_derives_and_filters_by_display_status(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $finished = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => TourDateStatus::Open,
        ]);
        $inProgress = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
            'status' => TourDateStatus::Open,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $finishedResponse = $this->actingAs($admin)
            ->get($this->url($tenant, 'status='.TourDateDisplayStatus::Finished->value));
        $finishedResponse->assertOk();
        $finishedResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 1)
            ->where('departures.data.0.id', $finished->id)
            ->where('departures.data.0.display_status', TourDateDisplayStatus::Finished->value)
        );

        $inProgressResponse = $this->actingAs($admin)
            ->get($this->url($tenant, 'status='.TourDateDisplayStatus::InProgress->value));
        $inProgressResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 1)
            ->where('departures.data.0.id', $inProgress->id)
            ->where('departures.data.0.display_status', TourDateDisplayStatus::InProgress->value)
        );
    }

    public function test_index_excludes_other_tenant_departures(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        TourDate::factory()->for($tourA)->create(['starts_at' => now()->addDays(3)]);
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        TourDate::factory()->for($tourB)->create(['starts_at' => now()->addDays(4)]);

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->get($this->url($tenantA));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 1)
            ->where('departures.data.0.tour.id', $tourA->id)
        );
    }

    private function assertSingle(Tenant $tenant, User $admin, string $query, int $expectedId): void
    {
        $response = $this->actingAs($admin)->get($this->url($tenant, $query));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('departures.data', 1)
            ->where('departures.data.0.id', $expectedId)
        );
    }

    private function url(Tenant $tenant, string $query = ''): string
    {
        return $this->host($tenant).'/admin/departures'.($query === '' ? '' : '?'.$query);
    }
}
