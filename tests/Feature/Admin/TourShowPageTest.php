<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TourShowPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_sees_the_show_page_with_tour_and_stats_props(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $tour = Tour::factory()->create(['currency' => 'USD']);

        $date = TourDate::factory()->for($tour)->create([
            'capacity' => 10,
            'booked_count' => 4,
            'status' => TourDateStatus::Open,
            'starts_at' => now()->addDays(5),
        ]);

        $confirmed = Booking::factory()
            ->for(User::factory())->for($tour)->for($date, 'tourDate')
            ->confirmed()
            ->create(['travelers_count' => 3, 'total_amount' => 300]);
        Booking::factory()
            ->for(User::factory())->for($tour)->for($date, 'tourDate')
            ->create(['travelers_count' => 2]);

        Payment::factory()->for($confirmed)->completed()->create(['amount' => 300]);

        $response = $this->actingAs($admin)->get('http://demo.montree.test/admin/tours/'.$tour->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tour/Show', false)
            ->where('tour.id', $tour->id)
            ->where('tour.status', $tour->status->value)
            ->where('stats.bookings.total', 2)
            ->where('stats.bookings.confirmed', 1)
            ->where('stats.bookings.pending_payment', 1)
            ->where('stats.travelers_total', 3)
            ->where('stats.revenue_total', '300.00')
            ->where('stats.currency', 'USD')
            ->where('stats.occupancy_upcoming.booked_total', 4)
            ->where('stats.occupancy_upcoming.capacity_total', 10)
            ->where('stats.occupancy_upcoming.rate', 40)
            ->where('stats.upcoming_dates_count', 1)
            ->whereNot('stats.next_date_starts_at', null)
        );
    }

    public function test_customer_role_is_forbidden(): void
    {
        $customer = $this->memberWithRole(UserRole::Customer);
        $tour = Tour::factory()->create();

        $this->actingAs($customer)
            ->get('http://demo.montree.test/admin/tours/'.$tour->id)
            ->assertForbidden();
    }

    public function test_tour_without_bookings_or_dates_returns_zeroed_stats(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $tour = Tour::factory()->create();

        $response = $this->actingAs($admin)->get('http://demo.montree.test/admin/tours/'.$tour->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tour/Show', false)
            ->where('stats.bookings.total', 0)
            ->where('stats.travelers_total', 0)
            ->where('stats.revenue_total', '0.00')
            ->where('stats.occupancy_upcoming.booked_total', 0)
            ->where('stats.occupancy_upcoming.capacity_total', 0)
            ->where('stats.occupancy_upcoming.rate', 0)
            ->where('stats.upcoming_dates_count', 0)
            ->where('stats.next_date_starts_at', null)
        );
    }

    public function test_admin_cannot_view_a_tour_from_another_tenant(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        $otherTenant->makeCurrent();
        $foreignTour = Tour::factory()->create();
        $this->tenant->makeCurrent();

        $this->actingAs($admin)
            ->get('http://demo.montree.test/admin/tours/'.$foreignTour->id)
            ->assertNotFound();
    }

    private function memberWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
