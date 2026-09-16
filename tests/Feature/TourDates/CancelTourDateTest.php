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

final class CancelTourDateTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_cancels_an_open_departure(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Open]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}/cancel",
            ['reason' => 'Clima adverso'],
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourDateStatus::Cancelled, $tourDate->fresh()?->status);
    }

    /**
     * El 409 JSON se volvió un error de formulario en la clave `tour_date`.
     */
    public function test_cancelling_an_already_cancelled_departure_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}/cancel",
        );

        $response->assertSessionHasErrors('tour_date');
    }

    public function test_a_guide_cannot_cancel_a_departure(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Open]);
        $guide = $this->guideFor($tenant);

        $this->actingAs($guide)->patch(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}/cancel",
        )->assertForbidden();

        $this->assertSame(TourDateStatus::Open, $tourDate->fresh()?->status);
    }

    public function test_cancelling_a_departure_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->patch(
            $this->host($tenantA)."/admin/tour-dates/{$dateB->id}/cancel",
        )->assertNotFound();
    }
}
