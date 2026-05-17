<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\NewsletterSubscriber;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Models\TourItinerary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_global_scope_isolates_tours_between_tenants(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        Tour::factory()->create(['name' => 'Tour A']);

        $tenantB->makeCurrent();
        Tour::factory()->create(['name' => 'Tour B']);

        $tenantA->makeCurrent();
        $this->assertSame(['Tour A'], Tour::query()->pluck('name')->all());
    }

    public function test_global_scope_isolates_categories_between_tenants(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        Category::factory()->create(['name' => 'Cat A', 'slug' => 'cat-a']);

        $tenantB->makeCurrent();
        Category::factory()->create(['name' => 'Cat B', 'slug' => 'cat-b']);

        $tenantA->makeCurrent();
        $this->assertSame(['Cat A'], Category::query()->pluck('name')->all());
    }

    public function test_global_scope_isolates_tour_images(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        TourImage::factory()->for($tourA)->create(['path' => 'a.jpg']);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        TourImage::factory()->for($tourB)->create(['path' => 'b.jpg']);

        $tenantA->makeCurrent();
        $this->assertSame(['a.jpg'], TourImage::query()->pluck('path')->all());
    }

    public function test_global_scope_isolates_tour_itineraries(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        TourItinerary::factory()->for($tourA)->create(['title' => 'Step A', 'step_number' => 1]);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        TourItinerary::factory()->for($tourB)->create(['title' => 'Step B', 'step_number' => 1]);

        $tenantA->makeCurrent();
        $this->assertSame(['Step A'], TourItinerary::query()->pluck('title')->all());
    }

    public function test_global_scope_isolates_tour_dates(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        TourDate::factory()->for($tourA)->create(['capacity' => 5]);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        TourDate::factory()->for($tourB)->create(['capacity' => 9]);

        $tenantA->makeCurrent();
        $this->assertSame([5], TourDate::query()->pluck('capacity')->all());
    }

    public function test_global_scope_isolates_promotions(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        Promotion::factory()->create(['code' => 'AAA']);

        $tenantB->makeCurrent();
        Promotion::factory()->create(['code' => 'BBB']);

        $tenantA->makeCurrent();
        $this->assertSame(['AAA'], Promotion::query()->pluck('code')->all());
    }

    public function test_global_scope_isolates_bookings(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        $dateA = TourDate::factory()->for($tourA)->create();
        Booking::factory()->for($userA)->for($tourA)->for($dateA, 'tourDate')->create();

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();
        Booking::factory()->for($userB)->for($tourB)->for($dateB, 'tourDate')->create();

        $tenantA->makeCurrent();
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame($userA->id, Booking::query()->first()?->user_id);
    }

    public function test_global_scope_isolates_booking_travelers(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        $dateA = TourDate::factory()->for($tourA)->create();
        $bookingA = Booking::factory()->for(User::factory())->for($tourA)->for($dateA, 'tourDate')->create();
        BookingTraveler::factory()->for($bookingA)->create(['full_name' => 'Traveler A']);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();
        $bookingB = Booking::factory()->for(User::factory())->for($tourB)->for($dateB, 'tourDate')->create();
        BookingTraveler::factory()->for($bookingB)->create(['full_name' => 'Traveler B']);

        $tenantA->makeCurrent();
        $this->assertSame(['Traveler A'], BookingTraveler::query()->pluck('full_name')->all());
    }

    public function test_global_scope_isolates_payments(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        $dateA = TourDate::factory()->for($tourA)->create();
        $bookingA = Booking::factory()->for(User::factory())->for($tourA)->for($dateA, 'tourDate')->create();
        Payment::factory()->for($bookingA)->create(['amount' => 100]);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();
        $bookingB = Booking::factory()->for(User::factory())->for($tourB)->for($dateB, 'tourDate')->create();
        Payment::factory()->for($bookingB)->create(['amount' => 999]);

        $tenantA->makeCurrent();
        $amounts = Payment::query()->pluck('amount')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([100.0], $amounts);
    }

    public function test_global_scope_isolates_reviews(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        $dateA = TourDate::factory()->for($tourA)->create();
        $bookingA = Booking::factory()->for(User::factory())->for($tourA)->for($dateA, 'tourDate')->create();
        Review::factory()->for($tourA)->for($bookingA)->for(User::factory())->create(['title' => 'Review A']);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();
        $bookingB = Booking::factory()->for(User::factory())->for($tourB)->for($dateB, 'tourDate')->create();
        Review::factory()->for($tourB)->for($bookingB)->for(User::factory())->create(['title' => 'Review B']);

        $tenantA->makeCurrent();
        $this->assertSame(['Review A'], Review::query()->pluck('title')->all());
    }

    public function test_global_scope_isolates_favorites(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        Favorite::factory()->for($tourA)->for(User::factory())->create();

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        Favorite::factory()->for($tourB)->for(User::factory())->create();

        $tenantA->makeCurrent();
        $this->assertSame(1, Favorite::query()->count());
    }

    public function test_global_scope_isolates_newsletter_subscribers(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        $tenantA->makeCurrent();
        NewsletterSubscriber::factory()->create(['email' => 'a@example.com']);

        $tenantB->makeCurrent();
        NewsletterSubscriber::factory()->create(['email' => 'b@example.com']);

        $tenantA->makeCurrent();
        $this->assertSame(['a@example.com'], NewsletterSubscriber::query()->pluck('email')->all());
    }

    public function test_tenant_configurations_are_isolated_per_tenant(): void
    {
        [$tenantA, $tenantB] = $this->twoTenants();

        TenantConfiguration::factory()->for($tenantA)->create(['primary_color' => '#aaaaaa']);
        TenantConfiguration::factory()->for($tenantB)->create(['primary_color' => '#bbbbbb']);

        $this->assertSame('#aaaaaa', $tenantA->configuration()->first()?->primary_color);
        $this->assertSame('#bbbbbb', $tenantB->configuration()->first()?->primary_color);
    }

    public function test_creating_a_tenant_scoped_model_without_current_tenant_throws(): void
    {
        Tenant::forgetCurrent();

        $this->expectException(\RuntimeException::class);

        Category::factory()->create();
    }

    /**
     * @return array{0: Tenant, 1: Tenant}
     */
    private function twoTenants(): array
    {
        return [
            Tenant::factory()->create(),
            Tenant::factory()->create(),
        ];
    }
}
