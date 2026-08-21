<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

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

/**
 * `operations` en el listado del panel: próxima salida, pasajeros, ocupación y
 * saldos, más los tres órdenes que se apoyan en esas cifras.
 */
final class TourIndexOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($this->tenant)->create();
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_reports_the_operational_summary_of_the_next_departure(): void
    {
        $admin = $this->member(UserRole::Admin);
        $tour = Tour::factory()->create(['name' => 'Cocora']);

        $past = TourDate::factory()->for($tour)->create(['starts_at' => now()->subDays(2), 'capacity' => 10, 'booked_count' => 9]);
        $next = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(3), 'capacity' => 12, 'booked_count' => 5]);
        $later = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(20), 'capacity' => 12, 'booked_count' => 12, 'status' => TourDateStatus::Full]);

        $this->bookingOn($tour, $next, travelers: 3, total: '600.00', paid: '600.00');
        $this->bookingOn($tour, $next, travelers: 2, total: '400.00', paid: '100.00');
        $this->bookingOn($tour, $later, travelers: 4, total: '800.00', paid: '0.00');
        $this->bookingOn($tour, $past, travelers: 6, total: '900.00', paid: '0.00');

        $response = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours');

        $response->assertOk();
        $response->assertJsonPath('data.0.operations.next_departure_at', $next->starts_at->toIso8601String());
        $response->assertJsonPath('data.0.operations.passengers_count', 5);
        $response->assertJsonPath('data.0.operations.occupancy.occupied', 5);
        $response->assertJsonPath('data.0.operations.occupancy.capacity', 12);
        $response->assertJsonPath('data.0.operations.passengers_with_due', 2);
    }

    public function test_a_tour_without_upcoming_departures_reports_an_empty_summary(): void
    {
        $admin = $this->member(UserRole::Admin);
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['starts_at' => now()->subDay(), 'capacity' => 10, 'booked_count' => 10]);
        TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(2), 'status' => TourDateStatus::Cancelled, 'capacity' => 10, 'booked_count' => 0]);

        $response = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours');

        $response->assertOk();
        $response->assertJsonPath('data.0.operations.next_departure_at', null);
        $response->assertJsonPath('data.0.operations.passengers_count', 0);
        $response->assertJsonPath('data.0.operations.occupancy.capacity', 0);
        $response->assertJsonPath('data.0.operations.passengers_with_due', 0);
    }

    public function test_the_summary_only_counts_departures_and_bookings_of_the_current_tenant(): void
    {
        $admin = $this->member(UserRole::Admin);
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->create(['starts_at' => now()->addDays(3), 'capacity' => 10, 'booked_count' => 4]);
        $this->bookingOn($tour, $departure, travelers: 2, total: '200.00', paid: '0.00');

        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($other)->create();
        $other->makeCurrent();
        $foreignTour = Tour::factory()->create();
        $foreignDeparture = TourDate::factory()->for($foreignTour)->create(['starts_at' => now()->addDay(), 'capacity' => 30, 'booked_count' => 30]);
        $this->bookingOn($foreignTour, $foreignDeparture, travelers: 9, total: '900.00', paid: '0.00');
        $this->tenant->makeCurrent();

        $response = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.operations.passengers_count', 2);
        $response->assertJsonPath('data.0.operations.occupancy.capacity', 10);
    }

    public function test_index_sorts_by_next_departure_occupancy_and_revenue(): void
    {
        $admin = $this->member(UserRole::Admin);

        $soon = Tour::factory()->create(['name' => 'Pronto']);
        TourDate::factory()->for($soon)->create(['starts_at' => now()->addDays(2), 'capacity' => 10, 'booked_count' => 9]);

        $late = Tour::factory()->create(['name' => 'Después']);
        $lateDeparture = TourDate::factory()->for($late)->create(['starts_at' => now()->addDays(40), 'capacity' => 10, 'booked_count' => 1]);
        $booking = $this->bookingOn($late, $lateDeparture, travelers: 2, total: '500.00', paid: '500.00');
        Payment::factory()->for($booking)->completed()->create(['amount' => '500.00']);

        $byDeparture = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/tours?sort=next_departure&direction=asc');
        $byDeparture->assertOk();
        $byDeparture->assertJsonPath('data.0.name', 'Pronto');
        $byDeparture->assertJsonPath('data.1.name', 'Después');

        $byOccupancy = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/tours?sort=occupancy&direction=desc');
        $byOccupancy->assertOk();
        $byOccupancy->assertJsonPath('data.0.name', 'Pronto');

        $byRevenue = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/tours?sort=revenue&direction=desc');
        $byRevenue->assertOk();
        $byRevenue->assertJsonPath('data.0.name', 'Después');
    }

    public function test_an_unknown_sort_falls_back_to_the_newest_tours(): void
    {
        $admin = $this->member(UserRole::Admin);
        Tour::factory()->create(['name' => 'Vieja', 'created_at' => now()->subDays(3)]);
        Tour::factory()->create(['name' => 'Nueva', 'created_at' => now()]);

        $response = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/tours?sort=drop table&direction=desc');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Nueva');
    }

    private function bookingOn(Tour $tour, TourDate $departure, int $travelers, string $total, string $paid): Booking
    {
        return Booking::factory()
            ->for(User::factory())
            ->for($tour)
            ->for($departure, 'tourDate')
            ->confirmed()
            ->create([
                'travelers_count' => $travelers,
                'adults_count' => $travelers,
                'minors_count' => 0,
                'subtotal' => $total,
                'total_amount' => $total,
                'paid_amount' => $paid,
            ]);
    }

    private function member(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
