<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
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

    public function test_the_page_carries_the_two_catalogs_at_once(): void
    {
        Provider::factory()->create();
        Hotel::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Logistics/Index')
                ->has('providers.data', 1)
                ->has('hotels.data', 3)
                ->where('filters.tab', 'providers')
            );
    }

    public function test_the_search_narrows_the_two_catalogs(): void
    {
        Provider::factory()->create(['name' => 'Transportes Cocora']);
        Hotel::factory()->create(['name' => 'Hostal del valle']);

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics?search=Cocora')
            ->assertInertia(fn (AssertableInertia $page) => $page
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

    public function test_the_page_never_shows_catalogs_of_another_tenant(): void
    {
        Provider::factory()->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        Hotel::factory()->count(2)->create();
        $this->tenant->makeCurrent();

        $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/logistics')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('providers.data', 1)
                ->has('hotels.data', 0)
            );
    }

    public function test_the_page_does_not_grow_its_query_count_with_more_records(): void
    {
        Provider::factory()->create();
        Hotel::factory()->create();

        $this->countQueries();
        $withOne = $this->countQueries();

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
