<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMembershipStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payment_confirms_booking_and_dispatches_notification(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addWeek()]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::PendingPayment,
            'total_amount' => '100000.00',
            'paid_amount' => '0.00',
        ]);

        $this->actingAs($user)
            ->postJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/payments", [
                'type' => 'full',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentStatus::Completed->value);

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        Notification::assertSentTo($user, BookingConfirmedNotification::class);
    }

    public function test_partial_payment_keeps_booking_pending(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addWeek()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::PendingPayment,
            'total_amount' => '100000.00',
            'paid_amount' => '0.00',
        ]);

        $this->actingAs($user)
            ->postJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/payments", [
                'type' => 'partial',
                'amount' => '30000.00',
            ])
            ->assertCreated();

        $this->assertSame(BookingStatus::PendingPayment, $booking->fresh()->status);
        Notification::assertNothingSent();
    }
}
