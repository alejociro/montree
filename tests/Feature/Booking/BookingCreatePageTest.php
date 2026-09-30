<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * T7: el checkout no se muestra para una salida que ya no admite reservas
 * —cerrada, agotada, cancelada o con su `booking_closes_at` vencido—. La
 * salida sigue viva en el admin; acá solo se cierra la puerta al viajero.
 */
final class BookingCreatePageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();
        $this->user = User::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_renders_checkout_for_a_bookable_departure(): void
    {
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertOk();
    }

    public function test_checkout_shows_the_departure_capacity_and_meeting_point_not_the_product_ones(): void
    {
        $tour = Tour::factory()->create([
            'status' => TourStatus::Active,
            'default_capacity' => 7,
            'meeting_point' => 'Plaza de Bolívar, Armenia',
        ]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'capacity' => 12,
            'meeting_point' => 'Terminal de Salento',
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Booking/Create')
                ->where('tourDate.capacity', 12)
                ->where('tourDate.meeting_point', 'Terminal de Salento'));
    }

    public function test_checkout_falls_back_to_the_product_meeting_point_when_not_customized(): void
    {
        $tour = Tour::factory()->create([
            'status' => TourStatus::Active,
            'meeting_point' => 'Plaza de Bolívar, Armenia',
        ]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'meeting_point' => null,
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tourDate.meeting_point', 'Plaza de Bolívar, Armenia'));
    }

    /**
     * T8 (revierte D7): una salida sin guía todavía se puede reservar — el
     * guía se asigna después, y esperar a que llegue dejaría en pausa
     * ventas que ya podrían cerrarse.
     */
    public function test_renders_checkout_for_a_departure_without_a_guide(): void
    {
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->withoutGuide()->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertOk();
    }

    public function test_returns_404_once_the_booking_window_has_closed(): void
    {
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => now()->subHour(),
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertNotFound();
    }

    public function test_returns_404_for_a_full_departure(): void
    {
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'status' => TourDateStatus::Full,
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertNotFound();
    }

    /**
     * T12: sin cierre propio, la regla general de cierre de reservas de la
     * agencia también esconde el checkout.
     */
    public function test_returns_404_once_the_agency_advance_hours_rule_has_closed_the_window(): void
    {
        $this->tenant->configuration()->create(['booking_advance_hours' => 24]);

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(10),
            'status' => TourDateStatus::Open,
            'booking_closes_at' => null,
        ]);

        $this->actingAs($this->user)
            ->get("http://demo.montree.test/booking/new?tour_date_id={$tourDate->id}")
            ->assertNotFound();
    }
}
