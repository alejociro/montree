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
        $route = Route::factory()->for($tour)->create(['is_default' => true]);
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

    /**
     * T7: sin enviar los bloques de "Contenido de la salida", la salida
     * nueva hereda del producto — las columnas quedan `null`.
     */
    public function test_store_leaves_content_blocks_null_when_not_sent(): void
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
            ],
        )->assertSessionHas('success');

        $departure = TourDate::query()->where('tour_id', $tour->id)->sole();
        $this->assertNull($departure->itinerary);
        $this->assertNull($departure->includes);
        $this->assertNull($departure->excludes);
        $this->assertNull($departure->requirements);
        $this->assertNull($departure->meeting_point);
        $this->assertNull($departure->booking_closes_at);
    }

    public function test_store_persists_custom_content_and_booking_closes_at(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $guide = $this->guideFor($tenant);
        $closesAt = now()->addDays(5);

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'guide_id' => $guide->id,
                'booking_closes_at' => $closesAt->toIso8601String(),
                'itinerary' => [
                    ['step_number' => 1, 'title' => 'Salida especial', 'description' => null, 'duration_label' => null],
                ],
                'includes' => ['Almuerzo especial'],
                'excludes' => ['Transporte'],
                'requirements' => ['Ropa de agua'],
                'meeting_point' => 'Punto especial de esta salida',
            ],
        )->assertSessionHas('success');

        $departure = TourDate::query()->where('tour_id', $tour->id)->sole();
        $this->assertSame('Salida especial', $departure->itinerary[0]['title']);
        $this->assertSame(['Almuerzo especial'], $departure->includes);
        $this->assertSame(['Transporte'], $departure->excludes);
        $this->assertSame(['Ropa de agua'], $departure->requirements);
        $this->assertSame('Punto especial de esta salida', $departure->meeting_point);
        $this->assertNotNull($departure->booking_closes_at);
        $this->assertTrue($departure->hasCustomContent());
    }

    public function test_store_rejects_a_booking_closes_at_after_the_start(): void
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
                'booking_closes_at' => now()->addDays(11)->toIso8601String(),
            ],
        )->assertSessionHasErrors('booking_closes_at');
    }

    public function test_store_rejects_a_booking_closes_at_in_the_past(): void
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
                'booking_closes_at' => now()->subHour()->toIso8601String(),
            ],
        )->assertSessionHasErrors('booking_closes_at');
    }
}
