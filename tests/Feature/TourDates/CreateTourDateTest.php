<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CreateTourDateTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_store_creates_an_open_departure_with_its_conditions(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $guide = $this->guideFor($tenant);
        $route = Route::factory()->create();
        $tour->routes()->attach($route->id, ['is_default' => true, 'position' => 1]);
        $provider = Provider::factory()->create();
        $hotel = Hotel::factory()->create();

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'price_override' => '950.00',
                'notes' => 'Salida especial',
                'guide_id' => $guide->id,
                'route_id' => $route->id,
                'provider_id' => $provider->id,
                'hotel_ids' => [$hotel->id],
            ],
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tour_dates', [
            'tour_id' => $tour->id,
            'route_id' => $route->id,
            'provider_id' => $provider->id,
            'guide_id' => $guide->id,
            'booked_count' => 0,
            'status' => TourDateStatus::Open->value,
        ]);
        $departure = TourDate::query()->where('tour_id', $tour->id)->sole();
        $this->assertSame([$hotel->id], $departure->hotels()->pluck('hotels.id')->all());
    }

    public function test_store_accepts_a_minimum_deposit_percentage_for_the_departure(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $guide = $this->guideFor($tenant);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'guide_id' => $guide->id,
                'min_payment_pct' => 50,
            ],
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tour_dates', ['tour_id' => $tour->id, 'min_payment_pct' => 50]);

        $this->actingAs($admin)
            ->get($this->host($tenant)."/admin/tours/{$tour->id}/edit")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('departures.0.min_payment_pct', 50)
                ->where('departures.0.effective_min_payment_pct', 50)
            );
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function invalidMinPaymentPercentages(): array
    {
        return ['cero' => [0], 'mayor que cien' => [101]];
    }

    #[DataProvider('invalidMinPaymentPercentages')]
    public function test_store_rejects_a_minimum_deposit_percentage_out_of_range(int $pct): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $guide = $this->guideFor($tenant);

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'guide_id' => $guide->id,
                'min_payment_pct' => $pct,
            ],
        )->assertSessionHasErrors('min_payment_pct');
    }

    public function test_store_rejects_a_past_start_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->subDay()->toIso8601String(), 'capacity' => 5],
        )->assertSessionHasErrors('starts_at');
    }

    public function test_store_rejects_a_guide_from_another_tenant(): void
    {
        $tenant = $this->makeTenant();
        $other = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $foreignGuide = $this->guideFor($other);
        $tenant->makeCurrent();

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->addDays(3)->toIso8601String(), 'capacity' => 5, 'guide_id' => $foreignGuide->id],
        )->assertSessionHasErrors('guide_id');
    }

    public function test_store_rejects_a_hotel_from_another_tenant(): void
    {
        $tenant = $this->makeTenant();
        $other = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tour = Tour::factory()->create(['tenant_id' => $tenant->id]);
        $other->makeCurrent();
        $foreignHotel = Hotel::factory()->create();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            ['starts_at' => now()->addDays(3)->toIso8601String(), 'capacity' => 5, 'hotel_ids' => [$foreignHotel->id]],
        )->assertSessionHasErrors('hotel_ids.0');
    }

    public function test_creating_a_departure_on_another_tenants_tour_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->post(
            $this->host($tenantA)."/admin/tours/{$tourB->id}/dates",
            ['starts_at' => now()->addDays(3)->toIso8601String(), 'capacity' => 5],
        )->assertNotFound();
    }
}
