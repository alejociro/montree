<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * El tablero de salidas servido por props Inertia.
 */
final class DeparturesIndexPageTest extends TestCase
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

    public function test_the_board_lists_the_departures_of_the_tenant_with_its_totals(): void
    {
        $tour = Tour::factory()->create();
        TourDate::factory()->count(3)->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'capacity' => 10,
            'booked_count' => 4,
        ]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Departures/Index')
                ->has('departures.data', 3)
                ->where('totals.departures', 3)
                ->where('totals.travellers', 12)
                ->where('stats.active', 3)
                ->has('counts')
                ->has('departureOptions.guides')
            );
    }

    public function test_the_scope_filter_narrows_the_board(): void
    {
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(6),
            'status' => TourDateStatus::Cancelled,
        ]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures?scope=disabled')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('departures.data', 1)
                ->where('filters.scope', 'disabled')
            );
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures?scope=inventado')
            ->assertSessionHasErrors('scope');
    }

    public function test_the_board_never_shows_departures_of_another_tenant(): void
    {
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        $foreignTour = Tour::factory()->create();
        TourDate::factory()->count(4)->for($foreignTour)->create(['starts_at' => now()->addDays(5)]);
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('departures.data', 1));
    }

    /**
     * El tablero pinta producto, guía, ruta, proveedor y hoteles de cada fila:
     * sin eager loading serían cinco consultas por salida.
     */
    public function test_the_board_does_not_grow_its_query_count_with_more_departures(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5), 'route_id' => $route->id]);

        // La primera visita calienta la caché de permisos de Spatie; medirla
        // contra una segunda contaría esa diferencia como si fuera N+1.
        $this->countQueries();
        $withOne = $this->countQueries();

        TourDate::factory()->count(6)->for($tour)->create(['starts_at' => now()->addDays(6), 'route_id' => $route->id]);

        $this->assertSame($withOne, $this->countQueries());
    }

    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get($this->host($this->tenant).'/admin/departures')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
