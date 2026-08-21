<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\TourDate;

use App\Enums\BookingStatus;
use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TourDateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_returns_upcoming_dates_by_default(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        TourDate::factory()->for($tour)->past()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates",
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_past_scope_returns_only_past_dates(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        TourDate::factory()->for($tour)->past()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates?scope=past",
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_store_creates_open_date_with_conditions(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $guide = $this->guideFor($tenant);
        $route = Route::factory()->create();
        $provider = Provider::factory()->create();
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'price_override' => '950.00',
                'notes' => 'Salida especial',
                'guide_id' => $guide->id,
                'route_id' => $route->id,
                'provider_id' => $provider->id,
                'hotel_ids' => [$hotel->id],
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.status', TourDateStatus::Open->value);
        $response->assertJsonPath('data.booked_count', 0);
        $response->assertJsonPath('data.guide.id', $guide->id);
        $response->assertJsonPath('data.hotels.0.id', $hotel->id);
        $this->assertDatabaseHas('tour_dates', [
            'tour_id' => $tour->id,
            'route_id' => $route->id,
            'provider_id' => $provider->id,
            'status' => TourDateStatus::Open->value,
        ]);
    }

    public function test_store_rejects_past_start_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->subDay()->toIso8601String(), 'capacity' => 5],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('starts_at');
    }

    public function test_store_rejects_guide_from_another_tenant(): void
    {
        $tenant = $this->makeTenant();
        $other = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $foreignGuide = $this->guideFor($other);
        $tenant->makeCurrent();

        $response = $this->actingAs($admin)->postJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->addDays(3)->toIso8601String(), 'capacity' => 5, 'guide_id' => $foreignGuide->id],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guide_id');
    }

    public function test_store_rejects_hotel_from_another_tenant(): void
    {
        $tenant = $this->makeTenant();
        $other = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tour = Tour::factory()->create(['tenant_id' => $tenant->id]);
        $other->makeCurrent();
        $foreignHotel = Hotel::factory()->create();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->addDays(3)->toIso8601String(), 'capacity' => 5, 'hotel_ids' => [$foreignHotel->id]],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('hotel_ids.0');
    }

    public function test_update_changes_capacity_price_and_conditions(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->withRoute()->withHotels()->create(['capacity' => 10]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $newRoute = Route::factory()->create();
        $newHotel = Hotel::factory()->create();

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
            [
                'capacity' => 15,
                'price_override' => '1200.00',
                'notes' => 'Condiciones actualizadas',
                'route_id' => $newRoute->id,
                'hotel_ids' => [$newHotel->id],
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.capacity', 15);
        $response->assertJsonPath('data.price_override', '1200.00');
        $response->assertJsonPath('data.route.id', $newRoute->id);
        $response->assertJsonPath('data.hotels.0.id', $newHotel->id);
        $response->assertJsonCount(1, 'data.hotels');
        $this->assertDatabaseHas('tour_dates', [
            'id' => $tourDate->id,
            'capacity' => 15,
            'route_id' => $newRoute->id,
        ]);
    }

    public function test_update_rejects_capacity_below_booked_count(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['capacity' => 10, 'booked_count' => 6]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
            ['capacity' => 4],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('capacity');
    }

    public function test_update_blocks_starts_at_change_with_active_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(5)]);
        Booking::factory()->for($tourDate)->create(['tour_id' => $tour->id, 'status' => BookingStatus::Confirmed]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
            ['starts_at' => now()->addDays(9)->toIso8601String()],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_DATE_HAS_BOOKINGS');
    }

    public function test_update_rejects_editing_cancelled_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
            ['capacity' => 20],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_DATE_CANCELLED');
    }

    public function test_destroy_removes_date_without_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('tour_dates', ['id' => $tourDate->id]);
    }

    public function test_destroy_blocked_when_date_has_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create();
        Booking::factory()->for($tourDate)->create(['tour_id' => $tour->id, 'status' => BookingStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}",
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_DATE_HAS_BOOKINGS');
    }

    public function test_tenant_isolation_update_other_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->putJson(
            "http://alpha.montree.test/api/v1/admin/tour-dates/{$dateB->id}",
            ['capacity' => 8],
        );

        $response->assertStatus(404);
    }

    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($tenant->id);
        Role::findOrCreate($role->value, 'web');
        $user->assignRole($role->value);

        return $user;
    }

    private function guideFor(Tenant $tenant): User
    {
        return $this->memberFor($tenant, UserRole::Guide);
    }
}
