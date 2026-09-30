<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourItinerary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7: la salida hereda del producto (itinerario, incluye, no incluye, qué
 * llevar, punto de encuentro) salvo que tenga su propio valor, y una salida
 * que ya no admite reservas —cerrada, agotada, o pasado su
 * `booking_closes_at`— no aparece en la ficha pública.
 */
final class PublicTourInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_a_departure_without_overrides_exposes_the_products_content(): void
    {
        $tour = Tour::factory()->create([
            'slug' => 'cocora',
            'status' => TourStatus::Active,
            'base_price' => '100000.00',
            'includes' => ['Guía certificado'],
            'excludes' => ['Transporte'],
            'requirements' => ['Bloqueador solar'],
            'meeting_point' => 'Plaza de Salento',
        ]);
        TourItinerary::factory()->for($tour)->create(['step_number' => 1, 'title' => 'Salida desde Armenia']);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'price_override' => null,
            'includes' => null,
            'excludes' => null,
            'requirements' => null,
            'meeting_point' => null,
        ]);

        $response = $this->getJson('http://demo.montree.test/api/v1/tours/cocora');

        $response->assertOk();
        $response->assertJsonPath('data.future_dates.0.effective_price', '100000.00');
        $response->assertJsonPath('data.future_dates.0.effective_includes.0', 'Guía certificado');
        $response->assertJsonPath('data.future_dates.0.effective_excludes.0', 'Transporte');
        $response->assertJsonPath('data.future_dates.0.effective_requirements.0', 'Bloqueador solar');
        $response->assertJsonPath('data.future_dates.0.effective_meeting_point', 'Plaza de Salento');
        $response->assertJsonPath('data.future_dates.0.effective_itinerary.0.title', 'Salida desde Armenia');
        $response->assertJsonPath('data.from_price', '100000.00');
    }

    public function test_a_departure_with_overrides_exposes_its_own_content(): void
    {
        $tour = Tour::factory()->create([
            'slug' => 'cocora-propia',
            'status' => TourStatus::Active,
            'base_price' => '100000.00',
            'includes' => ['Guía certificado'],
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'price_override' => '70000.00',
            'includes' => ['Almuerzo especial de esta salida'],
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/cocora-propia')
            ->assertOk()
            ->assertJsonPath('data.future_dates.0.effective_price', '70000.00')
            ->assertJsonPath('data.future_dates.0.effective_includes.0', 'Almuerzo especial de esta salida')
            ->assertJsonPath('data.from_price', '70000.00');
    }

    public function test_a_departure_past_its_booking_closes_at_is_not_listed(): void
    {
        $tour = Tour::factory()->create([
            'slug' => 'sin-cupo',
            'status' => TourStatus::Active,
            'base_price' => '50000.00',
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->subHour(),
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/sin-cupo')
            ->assertOk()
            ->assertJsonCount(0, 'data.future_dates')
            ->assertJsonPath('data.from_price', '50000.00');
    }

    public function test_a_departure_still_open_for_booking_is_listed_with_its_deadline(): void
    {
        $tour = Tour::factory()->create(['slug' => 'con-cierre', 'status' => TourStatus::Active]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->addDays(3),
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/con-cierre')
            ->assertOk()
            ->assertJsonCount(1, 'data.future_dates')
            ->assertJsonPath('data.future_dates.0.booking_closes_at', fn (?string $value) => $value !== null)
            ->assertJsonPath('data.future_dates.0.effective_booking_closes_at', fn (?string $value) => $value !== null);
    }

    /**
     * T12: sin cierre propio y sin regla de la agencia, la ficha pública no
     * expone `effective_booking_closes_at` —no hay nada real que mostrar—.
     */
    public function test_a_departure_without_any_close_rule_exposes_a_null_effective_booking_closes_at(): void
    {
        $tour = Tour::factory()->create(['slug' => 'sin-regla', 'status' => TourStatus::Active]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/sin-regla')
            ->assertOk()
            ->assertJsonPath('data.future_dates.0.booking_closes_at', null)
            ->assertJsonPath('data.future_dates.0.effective_booking_closes_at', null);
    }

    /**
     * T12: sin cierre propio, la regla de la agencia (horas antes del
     * inicio) es la que la ficha pública expone como cierre efectivo.
     */
    public function test_a_departure_without_its_own_close_exposes_the_agency_rule_as_effective(): void
    {
        $this->tenant->configuration()->create(['booking_advance_hours' => 24]);

        $tour = Tour::factory()->create(['slug' => 'regla-agencia', 'status' => TourStatus::Active]);
        $startsAt = now()->addDays(7);
        TourDate::factory()->for($tour)->create([
            'starts_at' => $startsAt,
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);

        $this->getJson('http://demo.montree.test/api/v1/tours/regla-agencia')
            ->assertOk()
            ->assertJsonPath(
                'data.future_dates.0.effective_booking_closes_at',
                $startsAt->copy()->subHours(24)->toIso8601String(),
            );
    }
}
