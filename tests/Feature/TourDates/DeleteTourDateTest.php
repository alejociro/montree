<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteTourDateTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_destroy_removes_a_departure_without_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('tour_dates', ['id' => $tourDate->id]);
    }

    public function test_destroy_is_blocked_when_the_departure_has_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create();
        Booking::factory()->for($tourDate)->create(['tour_id' => $tour->id, 'status' => BookingStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
        );

        $response->assertSessionHasErrors('tour_date');
        $this->assertDatabaseHas('tour_dates', ['id' => $tourDate->id]);
    }

    public function test_an_operator_cannot_delete_a_departure(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->actingAs($operator)
            ->delete($this->host($tenant)."/admin/tour-dates/{$tourDate->id}")
            ->assertForbidden();
    }

    public function test_deleting_a_departure_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->delete($this->host($tenantA)."/admin/tour-dates/{$dateB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('tour_dates', ['id' => $dateB->id]);
    }
}
