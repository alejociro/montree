<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Los tres catálogos de logística servidos por props Inertia.
 */
final class LogisticsIndexPageTest extends TestCase
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

    public function test_the_page_carries_the_three_catalogs_at_once(): void
    {
        Route::factory()->count(2)->create();
        Provider::factory()->create();
        Hotel::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Logistics/Index')
                ->has('routes.data', 2)
                ->has('providers.data', 1)
                ->has('hotels.data', 3)
                ->where('filters.tab', 'routes')
            );
    }

    public function test_the_search_narrows_the_three_catalogs(): void
    {
        Route::factory()->create(['name' => 'Sendero Cocora']);
        Route::factory()->create(['name' => 'Camino real']);
        Provider::factory()->create(['name' => 'Transportes Cocora']);
        Hotel::factory()->create(['name' => 'Hostal del valle']);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics?search=Cocora')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('routes.data', 1)
                ->has('providers.data', 1)
                ->has('hotels.data', 0)
                ->where('filters.search', 'Cocora')
            );
    }

    public function test_an_unknown_tab_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics?tab=inventado')
            ->assertSessionHasErrors('tab');
    }

    public function test_route_stops_travel_with_their_coordinates(): void
    {
        $route = Route::factory()->create();
        RouteStop::factory()->for($route)->create([
            'position' => 1,
            'latitude' => '4.6300000',
            'longitude' => '-75.4800000',
        ]);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('routes.data.0.stops.0.latitude', '4.6300000')
                ->where('routes.data.0.stops.0.longitude', '-75.4800000')
            );
    }

    public function test_the_page_never_shows_catalogs_of_another_tenant(): void
    {
        Route::factory()->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        Route::factory()->count(5)->create();
        Hotel::factory()->count(2)->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('routes.data', 1)
                ->has('hotels.data', 0)
            );
    }

    public function test_the_page_does_not_grow_its_query_count_with_more_records(): void
    {
        Route::factory()->has(RouteStop::factory()->count(2), 'stops')->create();
        Provider::factory()->create();
        Hotel::factory()->create();

        $this->countQueries();
        $withOne = $this->countQueries();

        Route::factory()->count(5)->has(RouteStop::factory()->count(2), 'stops')->create();
        Provider::factory()->count(5)->create();
        Hotel::factory()->count(5)->create();

        $this->assertSame($withOne, $this->countQueries());
    }

    private function countQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get($this->host($this->tenant).'/admin/logistics')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
