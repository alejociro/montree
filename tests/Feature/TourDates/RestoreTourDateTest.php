<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * El camino de vuelta de una salida inhabilitada.
 */
final class RestoreTourDateTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_restore_brings_a_disabled_departure_back(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'capacity' => 10,
            'booked_count' => 2,
            'status' => TourDateStatus::Cancelled,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant).'/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourDateStatus::Open, $date->fresh()?->status);
    }

    public function test_restore_returns_full_when_there_is_no_room_left(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'capacity' => 8,
            'booked_count' => 8,
            'status' => TourDateStatus::Cancelled,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant).'/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourDateStatus::Full, $date->fresh()?->status);
    }

    public function test_restore_rejects_a_departure_that_is_not_disabled(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'status' => TourDateStatus::Open,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant).'/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertSessionHasErrors('tour_date');
    }

    public function test_restore_is_forbidden_for_a_guide(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'status' => TourDateStatus::Cancelled,
        ]);
        $guide = $this->guideFor($tenant);

        $this->actingAs($guide)->patch(
            $this->host($tenant).'/admin/tour-dates/'.$date->id.'/restore',
        )->assertForbidden();
    }

    public function test_restoring_a_departure_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create(['status' => TourDateStatus::Cancelled]);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->patch(
            $this->host($tenantA).'/admin/tour-dates/'.$dateB->id.'/restore',
        )->assertNotFound();
    }
}
