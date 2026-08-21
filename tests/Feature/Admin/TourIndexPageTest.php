<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * KPIs del listado del panel. El dinero de los pasajeros —`pending_balance`— es
 * el único bloque con permiso propio: sin `bookings.view` no viaja, y no viaja
 * en cero.
 */
final class TourIndexPageTest extends TestCase
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
        TenantConfiguration::factory()->for($this->tenant)->create(['currency' => 'COP']);
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_sees_the_index_stats_of_the_current_tenant(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        Tour::factory()->count(2)->create(['status' => TourStatus::Draft]);
        Tour::factory()->create(['status' => TourStatus::Paused]);
        Tour::factory()->create(['status' => TourStatus::Archived]);

        $departure = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'capacity' => 10,
            'booked_count' => 4,
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(45),
            'capacity' => 8,
            'booked_count' => 8,
            'status' => TourDateStatus::Full,
        ]);

        Booking::factory()
            ->for(User::factory())->for($tour)->for($departure, 'tourDate')
            ->confirmed()
            ->create(['travelers_count' => 3, 'total_amount' => '900.00', 'paid_amount' => '300.00']);

        $response = $this->actingAs($admin)->get('http://demo.montree.test/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tour/Index', false)
            ->where('stats.tours.active', 1)
            ->where('stats.tours.draft', 2)
            ->where('stats.tours.paused', 1)
            ->where('stats.tours.archived', 1)
            ->where('stats.upcoming_departures.count', 1)
            ->whereNot('stats.upcoming_departures.next_starts_at', null)
            ->where('stats.occupancy.booked_seats', 4)
            ->where('stats.occupancy.total_capacity', 10)
            ->where('stats.occupancy.rate', 40)
            ->where('stats.pending_balance.passengers', 3)
            ->where('stats.pending_balance.amount', '600.00')
            ->where('stats.pending_balance.currency', 'COP')
        );
    }

    public function test_pending_balance_does_not_travel_without_the_bookings_permission(): void
    {
        $operator = $this->memberWithRole(UserRole::Operator);

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $departure = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
            'booked_count' => 2,
        ]);
        Booking::factory()
            ->for(User::factory())->for($tour)->for($departure, 'tourDate')
            ->confirmed()
            ->create(['travelers_count' => 2, 'total_amount' => '500.00', 'paid_amount' => '100.00']);

        $response = $this->actingAs($operator)->get('http://demo.montree.test/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tour/Index', false)
            ->where('stats.tours.active', 1)
            ->missing('stats.pending_balance')
        );
    }

    public function test_a_tenant_without_departures_or_bookings_gets_zeroed_stats(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        Tour::factory()->create(['status' => TourStatus::Draft]);

        $response = $this->actingAs($admin)->get('http://demo.montree.test/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.tours.active', 0)
            ->where('stats.upcoming_departures.count', 0)
            ->where('stats.upcoming_departures.next_starts_at', null)
            ->where('stats.occupancy.booked_seats', 0)
            ->where('stats.occupancy.total_capacity', 0)
            ->where('stats.occupancy.rate', 0)
            ->where('stats.pending_balance.passengers', 0)
            ->where('stats.pending_balance.amount', '0.00')
        );
    }

    public function test_the_stats_ignore_another_tenants_tours_departures_and_balances(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        Tour::factory()->create(['status' => TourStatus::Active]);

        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($other)->create();
        $other->makeCurrent();
        $foreignTour = Tour::factory()->create(['status' => TourStatus::Active]);
        $foreignDeparture = TourDate::factory()->for($foreignTour)->create([
            'starts_at' => now()->addDays(4),
            'capacity' => 20,
            'booked_count' => 15,
        ]);
        Booking::factory()
            ->for(User::factory())->for($foreignTour)->for($foreignDeparture, 'tourDate')
            ->confirmed()
            ->create(['travelers_count' => 5, 'total_amount' => '1000.00', 'paid_amount' => '0.00']);
        $this->tenant->makeCurrent();

        $response = $this->actingAs($admin)->get('http://demo.montree.test/admin/tours');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('stats.tours.active', 1)
            ->where('stats.upcoming_departures.count', 0)
            ->where('stats.occupancy.booked_seats', 0)
            ->where('stats.pending_balance.passengers', 0)
            ->where('stats.pending_balance.amount', '0.00')
        );
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
