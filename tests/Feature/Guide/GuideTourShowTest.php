<?php

declare(strict_types=1);

namespace Tests\Feature\Guide;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Detalle de tour en lectura del guía (D1): alcance por pertenencia y
 * `my_departures` con las suyas y nada más.
 */
final class GuideTourShowTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_guide_reads_the_detail_of_a_tour_where_they_have_a_departure(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $tour = Tour::factory()->create(['name' => 'Valle de Cocora']);
        $departure = $this->departureFor($guide, $tour);
        $this->passengerOn($this->bookingOn($departure, 2), ['full_name' => 'Ana Gomez']);
        Tenant::forgetCurrent();

        $response = $this->actingAs($guide)->getJson($this->host($tenant)."/api/v1/guide/tours/{$tour->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Valle de Cocora')
            ->assertJsonCount(1, 'data.my_departures')
            ->assertJsonPath('data.my_departures.0.id', $departure->id)
            ->assertJsonPath('data.my_departures.0.passengers_count', 2);
    }

    public function test_the_departures_of_other_guides_are_not_listed(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $colleague = $this->memberOf($tenant, UserRole::Guide);
        $tour = Tour::factory()->create();
        $mine = $this->departureFor($guide, $tour);
        $this->departureFor($colleague, $tour);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tours/{$tour->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.my_departures')
            ->assertJsonPath('data.my_departures.0.id', $mine->id);
    }

    public function test_a_tour_of_the_same_agency_without_a_departure_of_theirs_is_rejected(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $colleague = $this->memberOf($tenant, UserRole::Guide);
        $tour = Tour::factory()->create();
        $this->departureFor($colleague, $tour);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tours/{$tour->id}")
            ->assertForbidden();
    }

    public function test_a_tour_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->tenantAt('otra');
        $foreignTour = Tour::factory()->create();
        $this->departureFor($this->memberOf($other, UserRole::Guide), $foreignTour);

        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tours/{$foreignTour->id}")
            ->assertNotFound();
    }
}
