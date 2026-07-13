<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingTravelersControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User, 2: Booking}
     */
    private function setupBooking(int $adults = 2, int $minors = 1): array
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'capacity' => 20,
            'booked_count' => 0,
            'status' => TourDateStatus::Open,
        ]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $booking = Booking::factory()
            ->for($user)
            ->for($tour)
            ->for($tourDate)
            ->create(['adults_count' => $adults, 'minors_count' => $minors, 'travelers_count' => $adults + $minors]);

        return [$tenant, $user, $booking];
    }

    public function test_owner_can_add_travelers(): void
    {
        [$tenant, $user, $booking] = $this->setupBooking(2, 1);

        $response = $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [
                ['full_name' => 'Ana Perez', 'is_minor' => false, 'phone' => '+57 300 111 2233'],
                ['full_name' => 'Luis Perez', 'is_minor' => false],
                ['full_name' => 'Nino Perez', 'is_minor' => true, 'birth_date' => '2018-04-10'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(3, 'data.travelers')
            ->assertJsonPath('data.travelers.2.is_minor', true);

        $this->assertDatabaseCount('booking_travelers', 3);
        $this->assertDatabaseHas('booking_travelers', [
            'booking_id' => $booking->id,
            'full_name' => 'Nino Perez',
            'is_minor' => true,
        ]);
    }

    public function test_sync_updates_and_removes_travelers(): void
    {
        [$tenant, $user, $booking] = $this->setupBooking(2, 0);

        $keep = BookingTraveler::factory()->for($booking)->create(['full_name' => 'Old Name', 'is_minor' => false]);
        $remove = BookingTraveler::factory()->for($booking)->create(['full_name' => 'To Remove', 'is_minor' => false]);

        $response = $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [
                ['id' => $keep->id, 'full_name' => 'Updated Name', 'is_minor' => false],
            ],
        ]);

        $response->assertOk()->assertJsonCount(1, 'data.travelers');

        $this->assertDatabaseHas('booking_travelers', ['id' => $keep->id, 'full_name' => 'Updated Name']);
        $this->assertDatabaseMissing('booking_travelers', ['id' => $remove->id]);
    }

    public function test_rejects_when_traveler_counts_exceed_booking(): void
    {
        [$tenant, $user, $booking] = $this->setupBooking(2, 1);

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [
                ['full_name' => 'A', 'is_minor' => false],
                ['full_name' => 'B', 'is_minor' => false],
                ['full_name' => 'C', 'is_minor' => false],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('travelers');
    }

    public function test_rejects_when_booking_is_cancelled(): void
    {
        [$tenant, $user, $booking] = $this->setupBooking(2, 0);
        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [
                ['full_name' => 'A', 'is_minor' => false],
            ],
        ])->assertStatus(409)->assertJsonPath('error_code', 'BOOKING_TRAVELERS_LOCKED');
    }

    public function test_rejects_when_not_owner(): void
    {
        [$tenant, $user, $booking] = $this->setupBooking(2, 0);

        $other = User::factory()->create();
        $tenant->users()->attach($other->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($other)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [
                ['full_name' => 'A', 'is_minor' => false],
            ],
        ])->assertStatus(404);
    }

    public function test_cannot_sync_travelers_of_other_tenant_booking(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        $otherTenant->makeCurrent();
        $otherTour = Tour::factory()->create(['status' => TourStatus::Active]);
        $otherDate = TourDate::factory()->for($otherTour)->create(['starts_at' => now()->addDays(5), 'status' => TourDateStatus::Open]);
        $otherUser = User::factory()->create();
        $otherBooking = Booking::factory()->for($otherUser)->for($otherTour)->for($otherDate)->create([
            'adults_count' => 2, 'minors_count' => 0, 'travelers_count' => 2,
        ]);
        Tenant::forgetCurrent();

        [$tenant, $user, $booking] = $this->setupBooking(2, 0);

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$otherBooking->booking_number}/travelers", [
            'travelers' => [
                ['full_name' => 'A', 'is_minor' => false],
            ],
        ])->assertStatus(404);
    }
}
