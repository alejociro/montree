<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_sees_full_dashboard_payload(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP', 'timezone' => 'America/Bogota']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->active()->create(['name' => 'Senderismo Cocora']);
        $tourDate = TourDate::factory()->for($tour)->create([
            'capacity' => 12,
            'booked_count' => 8,
            'starts_at' => Carbon::parse('2026-05-18 07:00:00'),
            'ends_at' => Carbon::parse('2026-05-18 12:00:00'),
            'status' => TourDateStatus::Open,
        ]);

        $bookingA = Booking::factory()->confirmed()->create([
            'tour_id' => $tour->id,
            'tour_date_id' => $tourDate->id,
            'created_at' => Carbon::parse('2026-05-01'),
            'total_amount' => 240,
        ]);
        $bookingB = Booking::factory()->confirmed()->create([
            'tour_id' => $tour->id,
            'tour_date_id' => $tourDate->id,
            'created_at' => Carbon::parse('2026-05-10'),
        ]);
        $bookingC = Booking::factory()->create([
            'tour_id' => $tour->id,
            'tour_date_id' => $tourDate->id,
            'status' => BookingStatus::PendingPayment,
            'created_at' => Carbon::parse('2026-05-15'),
        ]);

        Payment::factory()->completed()->for($bookingA)->create([
            'amount' => 240,
            'processed_at' => Carbon::parse('2026-05-01 13:00:00'),
        ]);

        Review::factory()->approved()->create([
            'tour_id' => $tour->id,
            'booking_id' => $bookingA->id,
            'rating' => 5,
            'approved_at' => Carbon::parse('2026-05-02'),
        ]);
        Review::factory()->approved()->create([
            'tour_id' => $tour->id,
            'booking_id' => $bookingB->id,
            'rating' => 4,
            'approved_at' => Carbon::parse('2026-05-03'),
        ]);
        Review::factory()->create([
            'tour_id' => $tour->id,
            'booking_id' => $bookingC->id,
            'status' => ReviewStatus::Pending,
        ]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/dashboard?period=last_30_days',
        );

        $response->assertOk();
        $response->assertJsonPath('data.period.key', 'last_30_days');
        $response->assertJsonPath('data.revenue.gross', '240.00');
        $response->assertJsonPath('data.revenue.currency', 'COP');
        $response->assertJsonPath('data.bookings.total', 3);
        $response->assertJsonPath('data.bookings.confirmed', 2);
        $response->assertJsonPath('data.bookings.pending_payment', 1);
        $response->assertJsonPath('data.rating.average', '4.50');
        $response->assertJsonPath('data.rating.count', 2);
        $response->assertJsonPath('data.pending_reviews_count', 1);
        $response->assertJsonPath('data.permissions.can_export_reports', true);
        $response->assertJsonPath('data.top_tours.0.name', 'Senderismo Cocora');
        $response->assertJsonPath('data.top_tours.0.bookings_count', 3);
        $response->assertJsonPath('data.upcoming_dates.0.tour_name', 'Senderismo Cocora');

        Carbon::setTestNow();
    }

    public function test_operator_cannot_export_reports(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'op-demo', 'domain' => 'op-demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $response = $this->actingAs($operator)->getJson(
            'http://op-demo.montree.test/api/v1/admin/dashboard',
        );

        $response->assertOk();
        $response->assertJsonPath('data.permissions.can_export_reports', false);
    }

    public function test_growth_pct_is_null_when_previous_period_is_zero(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $tenant = Tenant::factory()->create(['slug' => 'fresh', 'domain' => 'fresh.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://fresh.montree.test/api/v1/admin/dashboard?period=last_30_days',
        );

        $response->assertOk();
        $response->assertJsonPath('data.bookings.growth_pct', null);
        $response->assertJsonPath('data.revenue.growth_pct', null);

        Carbon::setTestNow();
    }

    public function test_customer_cannot_access_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'no-go', 'domain' => 'no-go.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $customer = $this->memberFor($tenant, UserRole::Customer);

        $response = $this->actingAs($customer)->getJson(
            'http://no-go.montree.test/api/v1/admin/dashboard',
        );

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'anon', 'domain' => 'anon.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->getJson('http://anon.montree.test/api/v1/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_invalid_period_returns_validation_error(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'bad-period', 'domain' => 'bad-period.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://bad-period.montree.test/api/v1/admin/dashboard?period=invalid_key',
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['period']);
    }

    public function test_metrics_are_isolated_per_tenant(): void
    {
        Carbon::setTestNow('2026-05-17 12:00:00');

        $tenantA = Tenant::factory()->create(['slug' => 'iso-a', 'domain' => 'iso-a.montree.test']);
        TenantConfiguration::factory()->for($tenantA)->create();
        $tenantB = Tenant::factory()->create(['slug' => 'iso-b', 'domain' => 'iso-b.montree.test']);
        TenantConfiguration::factory()->for($tenantB)->create();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->active()->create();
        $bookingA = Booking::factory()->confirmed()->create([
            'tour_id' => $tourA->id,
            'created_at' => Carbon::parse('2026-05-01'),
        ]);
        Payment::factory()->completed()->for($bookingA)->create([
            'amount' => 999,
            'processed_at' => Carbon::parse('2026-05-01 13:00:00'),
        ]);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->active()->create();
        Booking::factory()->confirmed()->create([
            'tour_id' => $tourB->id,
            'created_at' => Carbon::parse('2026-05-02'),
        ]);

        Tenant::forgetCurrent();
        $adminB = $this->memberFor($tenantB, UserRole::Admin);

        $response = $this->actingAs($adminB)->getJson(
            'http://iso-b.montree.test/api/v1/admin/dashboard?period=last_30_days',
        );

        $response->assertOk();
        $response->assertJsonPath('data.revenue.gross', '0.00');
        $response->assertJsonPath('data.bookings.total', 1);

        Carbon::setTestNow();
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
