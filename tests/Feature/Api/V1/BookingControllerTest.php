<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantWithUser(int $capacity = 10): array
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['status' => TourStatus::Active, 'base_price' => '100000.00']);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'capacity' => $capacity,
            'booked_count' => 0,
            'status' => TourDateStatus::Open,
        ]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$tenant, $tour, $tourDate, $user];
    }

    public function test_creates_booking_when_capacity_available(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(10);

        $response = $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 3,
        ]);

        $response->assertCreated()->assertJsonPath('data.travelers_count', 3);
        $this->assertDatabaseHas('bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 3,
            'status' => BookingStatus::PendingPayment->value,
        ]);
        $this->assertEquals(3, $tourDate->fresh()->booked_count);
    }

    public function test_rejects_when_insufficient_capacity(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(2);

        $response = $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 5,
        ]);

        $response->assertStatus(409)->assertJsonPath('error_code', 'INSUFFICIENT_CAPACITY');
    }

    public function test_rejects_past_date(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subDay(),
            'status' => TourDateStatus::Open,
        ]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/bookings', [
                'tour_date_id' => $tourDate->id,
                'travelers_count' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'BOOKING_WINDOW_CLOSED');
    }

    public function test_show_returns_own_booking(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(10);

        $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 1,
        ])->assertCreated();

        $booking = Booking::query()->where('user_id', $user->id)->first();

        $this->actingAs($user)
            ->getJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertJsonPath('data.booking_number', $booking->booking_number);
    }

    public function test_show_returns_404_for_other_user(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(10);

        $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 1,
        ])->assertCreated();
        $booking = Booking::query()->where('user_id', $user->id)->first();

        $other = User::factory()->create();
        $tenant->users()->attach($other->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($other)
            ->getJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}")
            ->assertStatus(404);
    }
}
