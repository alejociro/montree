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
use App\Notifications\Auth\TenantAwareResetPassword;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'adults_count' => 2,
            'minors_count' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.travelers_count', 3)
            ->assertJsonPath('data.adults_count', 2)
            ->assertJsonPath('data.minors_count', 1);
        $this->assertDatabaseHas('bookings', [
            'tour_date_id' => $tourDate->id,
            'travelers_count' => 3,
            'adults_count' => 2,
            'minors_count' => 1,
            'status' => BookingStatus::PendingPayment->value,
        ]);
        $this->assertEquals(3, $tourDate->fresh()->booked_count);
    }

    public function test_rejects_when_adults_count_below_one(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(10);

        $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'adults_count' => 0,
            'minors_count' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('adults_count');
    }

    public function test_rejects_when_travelers_total_exceeds_limit(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(100);

        $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'adults_count' => 48,
            'minors_count' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors('adults_count');
    }

    public function test_guest_booking_creates_account_pending_password_setup(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        [$tenant, $tour, $tourDate] = $this->setupTenantWithUser(10);

        $response = $this->postJson('http://demo.montree.test/api/v1/bookings', [
            'email' => 'guest@example.com',
            'email_confirmation' => 'guest@example.com',
            'full_name' => 'Guest Traveler',
            'phone' => '+57 300 000 0000',
            'tour_date_id' => $tourDate->id,
            'adults_count' => 2,
            'minors_count' => 0,
        ]);

        $response->assertCreated();

        $guest = User::where('email', 'guest@example.com')->firstOrFail();

        $this->assertNull($guest->password_set_at);
        $this->assertTrue($guest->mustSetPassword());

        Notification::assertSentTo($guest, TenantAwareResetPassword::class);
    }

    public function test_rejects_when_insufficient_capacity(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(2);

        $response = $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'adults_count' => 5,
            'minors_count' => 0,
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
                'adults_count' => 1,
                'minors_count' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'BOOKING_WINDOW_CLOSED');
    }

    public function test_show_returns_own_booking(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->setupTenantWithUser(10);

        $this->actingAs($user)->postJson('http://demo.montree.test/api/v1/bookings', [
            'tour_date_id' => $tourDate->id,
            'adults_count' => 1,
            'minors_count' => 0,
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
            'adults_count' => 1,
            'minors_count' => 0,
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
