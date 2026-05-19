<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompleteJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_journey_catalog_to_booking_to_review(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($tenant)->create([
            'reviews_require_moderation' => false,
            'require_traveler_details' => false,
        ]);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create([
            'status' => TourStatus::Active,
            'base_price' => '50000.00',
            'currency' => 'COP',
        ]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'capacity' => 20,
            'booked_count' => 0,
            'status' => TourDateStatus::Open,
        ]);

        $customer = User::factory()->create();
        $tenant->users()->attach($customer->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->get('http://demo.montree.test/tours')
            ->assertOk();

        $this->get("http://demo.montree.test/tours/{$tour->slug}")
            ->assertOk();

        $bookingResponse = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/bookings', [
                'tour_date_id' => $tourDate->id,
                'travelers_count' => 2,
            ]);

        $bookingResponse->assertCreated();
        $bookingNumber = $bookingResponse->json('data.booking_number');

        $this->assertDatabaseHas('bookings', [
            'booking_number' => $bookingNumber,
            'status' => BookingStatus::PendingPayment->value,
            'travelers_count' => 2,
        ]);

        $booking = Booking::query()->where('booking_number', $bookingNumber)->first();

        $this->actingAs($customer)
            ->postJson("http://demo.montree.test/api/v1/bookings/{$bookingNumber}/payments")
            ->assertCreated();

        $booking->refresh();
        $this->assertEquals(BookingStatus::Confirmed->value, $booking->status->value);

        $booking->update(['status' => BookingStatus::Completed]);

        $reviewResponse = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
                'title' => 'Increíble tour',
                'comment' => 'La mejor experiencia de ecoturismo que he tenido.',
            ]);

        $reviewResponse->assertCreated();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'rating' => 5,
            'status' => ReviewStatus::Approved->value,
        ]);

        $publicReviews = $this->getJson("http://demo.montree.test/api/v1/tours/{$tour->slug}/reviews");
        $publicReviews->assertOk();
        $this->assertCount(1, $publicReviews->json('data'));
    }
}
