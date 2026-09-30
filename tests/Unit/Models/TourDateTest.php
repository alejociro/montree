<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\TourDateStatus;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourItinerary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La salida hereda del producto (T7): columna `null` = hereda y sigue los
 * cambios futuros del tour; con valor = personalizada. Estos métodos
 * `effective*()` son la única fuente de verdad de esa herencia — los
 * resources y acciones ya no repiten `price_override ?? tour->base_price`.
 */
class TourDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_effective_price_falls_back_to_tour_base_price(): void
    {
        $this->makeTenant()->makeCurrent();

        $tour = Tour::factory()->create(['base_price' => '150000.00']);
        $tourDate = TourDate::factory()->for($tour)->create(['price_override' => null]);

        $this->assertSame('150000.00', $tourDate->effectivePrice());
    }

    public function test_effective_price_prefers_override(): void
    {
        $this->makeTenant()->makeCurrent();

        $tour = Tour::factory()->create(['base_price' => '150000.00']);
        $tourDate = TourDate::factory()->for($tour)->create(['price_override' => '99000.00']);

        $this->assertSame('99000.00', $tourDate->effectivePrice());
    }

    public function test_effective_price_follows_future_changes_to_the_product(): void
    {
        $this->makeTenant()->makeCurrent();

        $tour = Tour::factory()->create(['base_price' => '150000.00']);
        $tourDate = TourDate::factory()->for($tour)->create(['price_override' => null]);

        $tour->update(['base_price' => '200000.00']);
        $tourDate->refresh()->load('tour');

        $this->assertSame('200000.00', $tourDate->effectivePrice());
    }

    public function test_effective_content_blocks_inherit_from_tour_when_null(): void
    {
        $this->makeTenant()->makeCurrent();

        $tour = Tour::factory()->create([
            'includes' => ['Guía'],
            'excludes' => ['Transporte'],
            'requirements' => ['Bloqueador'],
            'meeting_point' => 'Plaza principal',
        ]);
        TourItinerary::factory()->for($tour)->create(['step_number' => 1, 'title' => 'Salida']);

        $tourDate = TourDate::factory()->for($tour)->create([
            'itinerary' => null,
            'includes' => null,
            'excludes' => null,
            'requirements' => null,
            'meeting_point' => null,
        ]);
        $tourDate->load('tour.itineraries');

        $this->assertSame(['Guía'], $tourDate->effectiveIncludes());
        $this->assertSame(['Transporte'], $tourDate->effectiveExcludes());
        $this->assertSame(['Bloqueador'], $tourDate->effectiveRequirements());
        $this->assertSame('Plaza principal', $tourDate->effectiveMeetingPoint());
        $this->assertSame('Salida', $tourDate->effectiveItinerary()[0]['title']);
        $this->assertFalse($tourDate->hasCustomContent());
    }

    public function test_effective_content_blocks_use_own_override_when_set(): void
    {
        $this->makeTenant()->makeCurrent();

        $tour = Tour::factory()->create(['includes' => ['Guía']]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'includes' => ['Almuerzo propio de la salida'],
        ]);

        $this->assertSame(['Almuerzo propio de la salida'], $tourDate->effectiveIncludes());
        $this->assertTrue($tourDate->hasCustomContent());
    }

    public function test_is_bookable_false_when_status_is_not_open(): void
    {
        $this->makeTenant()->makeCurrent();

        $tourDate = TourDate::factory()->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Full,
        ]);

        $this->assertFalse($tourDate->isBookable());
    }

    public function test_is_bookable_false_when_starts_at_is_past(): void
    {
        $this->makeTenant()->makeCurrent();

        $tourDate = TourDate::factory()->past()->create([
            'status' => TourDateStatus::Open,
        ]);

        $this->assertFalse($tourDate->isBookable());
    }

    public function test_is_bookable_false_once_booking_closes_at_has_passed(): void
    {
        $this->makeTenant()->makeCurrent();

        $tourDate = TourDate::factory()->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->subMinute(),
        ]);

        $this->assertFalse($tourDate->isBookable());
    }

    public function test_is_bookable_true_when_booking_closes_at_is_still_future(): void
    {
        $this->makeTenant()->makeCurrent();

        $tourDate = TourDate::factory()->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->addDay(),
        ]);

        $this->assertTrue($tourDate->isBookable());
    }

    public function test_scope_bookable_matches_is_bookable(): void
    {
        $this->makeTenant()->makeCurrent();

        $open = TourDate::factory()->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->subMinute(),
        ]);

        $ids = TourDate::query()->bookable()->pluck('id')->all();

        $this->assertSame([$open->id], $ids);
    }

    /**
     * T12: sin regla de la agencia (`booking_advance_hours` null) y sin
     * cierre propio, el comportamiento es el de siempre —hasta la hora de
     * salida—.
     */
    public function test_effective_booking_closes_at_falls_back_to_starts_at_without_any_rule(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration()->update(['booking_advance_hours' => null]);
        $tenant->makeCurrent();

        // WHY: `Model::setAttribute()` formatea el atributo de fecha como
        // string al guardarlo (sin microsegundos); truncarlo acá también
        // evita comparar dos instantes que difieren solo en microsegundos.
        $startsAt = now()->addDays(3)->startOfSecond();
        $tourDate = TourDate::factory()->create([
            'starts_at' => $startsAt,
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);

        $this->assertTrue($tourDate->effectiveBookingClosesAt()->equalTo($startsAt));
        $this->assertFalse($tourDate->hasBookingDeadline());
        $this->assertTrue($tourDate->isBookable());
    }

    /**
     * T12: con regla de 24 horas y sin cierre propio, una salida a 10 horas
     * ya no admite reservas nuevas y una a 30 horas todavía sí.
     */
    public function test_the_agency_advance_hours_rule_closes_a_departure_without_its_own_close_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration()->update(['booking_advance_hours' => 24]);
        $tenant->makeCurrent();

        $closedSoon = TourDate::factory()->create([
            'starts_at' => now()->addHours(10),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);
        $stillOpen = TourDate::factory()->create([
            'starts_at' => now()->addHours(30),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);

        $this->assertFalse($closedSoon->isBookable());
        $this->assertTrue($stillOpen->isBookable());
        $this->assertTrue($closedSoon->hasBookingDeadline());

        $ids = TourDate::query()->bookable()->pluck('id')->all();
        $this->assertSame([$stillOpen->id], $ids);
    }

    /**
     * T12: el cierre propio de la salida manda sobre la regla de la
     * agencia, tanto si es más temprano como si es más tarde.
     */
    public function test_the_departures_own_close_date_overrides_the_agency_rule(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration()->update(['booking_advance_hours' => 24]);
        $tenant->makeCurrent();

        // La regla (30h - 24h = 6h) todavía dejaría reservar; el propio
        // cierre, ya pasado, manda y la deja cerrada.
        $ownCloseIsEarlier = TourDate::factory()->create([
            'starts_at' => now()->addHours(30),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->subHour(),
        ]);

        // La regla cerraría esta salida (10h de inicio, regla de 24h), pero
        // su propio cierre —más tarde, a 5h— manda y sigue abierta.
        $ownCloseIsLater = TourDate::factory()->create([
            'starts_at' => now()->addHours(10),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->addHours(5),
        ]);

        $this->assertFalse($ownCloseIsEarlier->isBookable());
        $this->assertTrue($ownCloseIsLater->isBookable());

        $ids = TourDate::query()->bookable()->pluck('id')->all();
        $this->assertSame([$ownCloseIsLater->id], $ids);
    }

    private function makeTenant(): Tenant
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'demo-'.uniqid(),
            'domain' => uniqid('demo-', true).'.montree.test',
        ]);
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }
}
