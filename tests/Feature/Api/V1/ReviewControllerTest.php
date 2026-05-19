<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(bool $moderationOn = true): array
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create([
            'reviews_require_moderation' => $moderationOn,
        ]);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->subDay()]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$tenant, $tour, $tourDate, $user];
    }

    public function test_customer_can_review_completed_booking(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->makeScenario(true);
        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::Completed,
        ]);

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Excelente experiencia',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', ReviewStatus::Pending->value);
    }

    public function test_rejects_review_for_non_completed_booking(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->makeScenario();
        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'BOOKING_NOT_COMPLETED');
    }

    public function test_rejects_duplicate_review(): void
    {
        [$tenant, $tour, $tourDate, $user] = $this->makeScenario();
        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::Completed,
        ]);
        Review::factory()->for($tour)->for($user)->for($booking)->create();

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/reviews', [
                'booking_id' => $booking->id,
                'rating' => 4,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'REVIEW_ALREADY_EXISTS');
    }

    public function test_admin_can_approve_review_and_recalculates_tour_rating(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        [$tenant, $tour, $tourDate, $user] = $this->makeScenario();
        $booking = Booking::factory()->for($user)->for($tour)->for($tourDate, 'tourDate')->create([
            'status' => BookingStatus::Completed,
        ]);
        $review = Review::factory()->for($tour)->for($user)->for($booking)->create([
            'status' => ReviewStatus::Pending,
            'rating' => 5,
        ]);

        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        setPermissionsTeamId($tenant->id);
        $admin->syncRoles([UserRole::Admin->value]);

        $this->actingAs($admin)
            ->patchJson("http://demo.montree.test/api/v1/admin/reviews/{$review->id}/status", [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Approved->value);

        $this->assertSame(1, $tour->fresh()->rating_count);
    }
}
