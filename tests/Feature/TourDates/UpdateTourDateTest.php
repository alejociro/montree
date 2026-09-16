<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateTourDateTest extends TestCase
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

    public function test_update_changes_capacity_price_and_conditions(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->withRoute()->withHotels()->create(['capacity' => 10]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $newRoute = Route::factory()->create();
        $tour->routes()->attach($newRoute->id, ['is_default' => true, 'position' => 1]);
        $newHotel = Hotel::factory()->create();

        $response = $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
            [
                'capacity' => 15,
                'price_override' => '1200.00',
                'notes' => 'Condiciones actualizadas',
                'route_id' => $newRoute->id,
                'hotel_ids' => [$newHotel->id],
            ],
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tour_dates', [
            'id' => $tourDate->id,
            'capacity' => 15,
            'price_override' => '1200.00',
            'route_id' => $newRoute->id,
        ]);
        $this->assertSame([$newHotel->id], $tourDate->fresh()?->hotels()->pluck('hotels.id')->all());
    }

    /** `null` limpia el override: la salida vuelve al porcentaje de la agencia. */
    public function test_update_clears_the_override_with_a_null_percentage(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tenant->configuration->update(['min_partial_payment_pct' => 30]);
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->withMinPaymentPct(70)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
            ['min_payment_pct' => null],
        );

        $response->assertSessionHas('success');
        $this->assertNull($tourDate->fresh()?->min_payment_pct);

        $this->actingAs($admin)
            ->get($this->host($tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.0.min_payment_pct', null)
                ->where('departures.0.effective_min_payment_pct', 30)
            );
    }

    public function test_update_rejects_a_capacity_below_the_booked_count(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['capacity' => 10, 'booked_count' => 6]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
            ['capacity' => 4],
        )->assertSessionHasErrors('capacity');
    }

    public function test_update_blocks_a_starts_at_change_with_active_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        Booking::factory()->for($tourDate)->create(['tour_id' => $tour->id, 'status' => BookingStatus::Confirmed]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
            ['starts_at' => now()->addDays(9)->toIso8601String()],
        )->assertSessionHasErrors('tour_date');
    }

    public function test_update_rejects_editing_a_cancelled_departure(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$tourDate->id}",
            ['capacity' => 20],
        )->assertSessionHasErrors('tour_date');
    }

    public function test_updating_a_departure_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->put(
            $this->host($tenantA)."/admin/tour-dates/{$dateB->id}",
            ['capacity' => 8],
        )->assertNotFound();
    }
}
