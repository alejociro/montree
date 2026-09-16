<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * El listado por producto dejó de ser un endpoint con `scope`: la pantalla de
 * edición recibe TODAS las salidas del tour —pasadas incluidas— y es el cliente
 * quien decide cuál bandeja pinta.
 */
final class TourEditDeparturesTest extends TestCase
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

    public function test_the_edit_page_carries_past_and_upcoming_departures(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $past = TourDate::factory()->for($tour)->past()->create();
        $upcoming = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->get($this->host($tenant)."/admin/tours/{$tour->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Tour/Edit', false)
            ->has('departures', 2)
            ->where('departures.0.id', $past->id)
            ->where('departures.1.id', $upcoming->id)
            ->has('departureOptions')
            ->has('departureDefaults')
            ->has('tour.routes')
        );
    }

    public function test_the_edit_page_only_carries_departures_of_its_own_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $other = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        TourDate::factory()->for($other)->create(['starts_at' => now()->addDays(6)]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->get($this->host($tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('departures', 1));
    }

    public function test_a_sales_member_cannot_open_the_edit_page(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->get($this->host($tenant)."/admin/tours/{$tour->id}/edit")
            ->assertForbidden();
    }

    public function test_the_edit_page_of_another_tenants_tour_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->get($this->host($tenantA)."/admin/tours/{$tourB->id}/edit")
            ->assertNotFound();
    }
}
