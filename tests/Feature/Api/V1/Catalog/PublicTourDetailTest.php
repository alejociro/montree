<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Catalog;

use App\Enums\ReviewStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Models\TourItinerary;
use App\Models\TourStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicTourDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_tour_with_full_payload(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create([
            'slug' => 'cocora-hike',
            'status' => TourStatus::Active,
        ]);
        TourImage::factory()->for($tour)->create(['is_cover' => true]);
        TourItinerary::factory()->for($tour)->create(['step_number' => 1]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
        ]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/cocora-hike');

        $response->assertOk()->assertJsonPath('data.slug', 'cocora-hike');
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'images', 'itinerary', 'stops', 'future_dates', 'rating_distribution'],
        ]);
    }

    public function test_exposes_route_stops_as_numbers_in_order(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['slug' => 'cocora-stops', 'status' => TourStatus::Active]);
        TourStop::factory()->drop()->for($tour)->create([
            'position' => 2,
            'name' => 'Terminal de Armenia',
            'latitude' => 4.5252,
            'longitude' => -75.6812,
        ]);
        TourStop::factory()->pickup()->for($tour)->create([
            'position' => 1,
            'name' => 'Plaza de Bolívar',
            'latitude' => 4.5350,
            'longitude' => -75.6813,
        ]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/cocora-stops');

        $response->assertOk();
        $response->assertJsonPath('data.stops.0.name', 'Plaza de Bolívar');
        $response->assertJsonPath('data.stops.1.name', 'Terminal de Armenia');
        $this->assertSame(4.535, $response->json('data.stops.0.latitude'));
    }

    public function test_returns_404_for_archived_tour(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        Tour::factory()->create(['slug' => 'archived', 'status' => TourStatus::Archived]);

        $this->getJson('http://demo.montree.test/api/v1/tours/archived')->assertNotFound();
    }

    public function test_reviews_endpoint_only_returns_approved(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['slug' => 't1', 'status' => TourStatus::Active]);
        Review::factory()->for($tour)->create(['status' => ReviewStatus::Approved, 'approved_at' => now()]);
        Review::factory()->for($tour)->create(['status' => ReviewStatus::Pending]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/t1/reviews');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_rating_distribution_keeps_the_star_of_every_count(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['slug' => 'cocora-rated', 'status' => TourStatus::Active]);
        Review::factory()->count(2)->for($tour)->create([
            'rating' => 5,
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
        ]);
        Review::factory()->for($tour)->create([
            'rating' => 4,
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
        ]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/cocora-rated');

        // WHY: con claves numéricas el resource re-indexaba el mapa y lo mandaba
        // como lista, así que el detalle pintaba los conteos en la estrella errónea.
        $response->assertOk()
            ->assertJsonPath('data.rating_distribution.5', 2)
            ->assertJsonPath('data.rating_distribution.4', 1)
            ->assertJsonPath('data.rating_distribution.3', 0)
            ->assertJsonPath('data.rating_distribution.2', 0)
            ->assertJsonPath('data.rating_distribution.1', 0);
    }

    public function test_favorite_toggle_requires_auth(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);

        $this->postJson('http://demo.montree.test/api/v1/favorites', ['tour_id' => $tour->id])
            ->assertUnauthorized();
    }

    public function test_favorite_toggle_creates_then_removes(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/favorites', ['tour_id' => $tour->id])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'tour_id' => $tour->id]);

        $this->actingAs($user)
            ->postJson('http://demo.montree.test/api/v1/favorites', ['tour_id' => $tour->id])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'tour_id' => $tour->id]);
    }
}
