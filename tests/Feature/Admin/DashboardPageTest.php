<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
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

/**
 * El panel de la agencia ya no se pide por API: llega por props de Inertia.
 */
final class DashboardPageTest extends TestCase
{
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

    public function test_admin_sees_the_full_snapshot_with_the_breakdown_by_method(): void
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
        Booking::factory()->create([
            'tour_id' => $tour->id,
            'tour_date_id' => $tourDate->id,
            'status' => BookingStatus::PendingPayment,
            'created_at' => Carbon::parse('2026-05-15'),
        ]);

        Payment::factory()->completed()->for($bookingA)->create([
            'gateway' => PaymentGateway::PlaceToPay,
            'amount' => 240,
            'processed_at' => Carbon::parse('2026-05-01 13:00:00'),
        ]);
        Payment::factory()->completed()->for($bookingB)->create([
            'gateway' => PaymentGateway::Cash,
            'amount' => 60,
            'processed_at' => Carbon::parse('2026-05-02 13:00:00'),
        ]);

        Review::factory()->approved()->create([
            'tour_id' => $tour->id,
            'booking_id' => $bookingA->id,
            'rating' => 5,
            'approved_at' => Carbon::parse('2026-05-02'),
        ]);
        Review::factory()->create([
            'tour_id' => $tour->id,
            'booking_id' => $bookingB->id,
            'status' => ReviewStatus::Pending,
        ]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->get('http://demo.montree.test/admin/dashboard?period=last_30_days')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('filters.period', 'last_30_days')
                ->where('snapshot.period.key', 'last_30_days')
                ->where('snapshot.revenue.gross', '300.00')
                ->where('snapshot.revenue.currency', 'COP')
                ->where('snapshot.revenue.by_method', [
                    ['method' => 'placetopay', 'label' => PaymentGateway::PlaceToPay->label(), 'amount' => '240.00', 'share_pct' => 80],
                    ['method' => 'cash', 'label' => PaymentGateway::Cash->label(), 'amount' => '60.00', 'share_pct' => 20],
                    ['method' => 'transfer', 'label' => PaymentGateway::Transfer->label(), 'amount' => '0.00', 'share_pct' => 0],
                ])
                ->where('snapshot.bookings.total', 3)
                ->where('snapshot.bookings.confirmed', 2)
                ->where('snapshot.bookings.pending_payment', 1)
                ->where('snapshot.rating.average', '5.00')
                ->where('snapshot.pending_reviews_count', 1)
                ->where('snapshot.permissions.can_export_reports', true)
                ->where('snapshot.top_tours.0.name', 'Senderismo Cocora')
                ->where('snapshot.upcoming_dates.0.tour_name', 'Senderismo Cocora')
                ->has('periods', 6)
                ->where('periods.1.value', 'last_30_days')
            );

        Carbon::setTestNow();
    }

    public function test_the_operator_cannot_export_reports(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'op-demo', 'domain' => 'op-demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->actingAs($operator)
            ->get('http://op-demo.montree.test/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.permissions.can_export_reports', false)
                ->where('filters.period', 'last_30_days')
            );
    }

    public function test_an_invalid_period_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'bad-period', 'domain' => 'bad-period.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->get('http://bad-period.montree.test/admin/dashboard?period=invalid_key')
            ->assertStatus(302)
            ->assertSessionHasErrors(['period']);
    }

    public function test_a_customer_cannot_reach_the_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'no-go', 'domain' => 'no-go.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $customer = $this->memberFor($tenant, UserRole::Customer);

        $this->actingAs($customer)
            ->get('http://no-go.montree.test/admin/dashboard')
            ->assertForbidden();
    }

    public function test_the_snapshot_is_isolated_per_tenant(): void
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
            'gateway' => PaymentGateway::Cash,
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

        $this->actingAs($adminB)
            ->get('http://iso-b.montree.test/admin/dashboard?period=last_30_days')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.revenue.gross', '0.00')
                ->where('snapshot.revenue.by_method.1.amount', '0.00')
                ->where('snapshot.bookings.total', 1)
            );

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
