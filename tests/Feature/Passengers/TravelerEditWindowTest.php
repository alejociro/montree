<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\BookingStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * D10 — ventana de edición de la planilla por el titular de la reserva: hasta
 * `montree.passengers.traveler_edit_cutoff_hours` antes de la salida. La agencia
 * no queda dentro de la ventana: el cambio de última hora se hace por el panel.
 */
final class TravelerEditWindowTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    private const DEPARTURE = '2026-09-14 06:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_owner_edits_travelers_25_hours_before_departure(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(25));

        $response = $this->actingAs($booking->user)->putJson(
            $this->host($tenant)."/api/v1/bookings/{$booking->booking_number}/travelers",
            ['travelers' => [['full_name' => 'Ana Perez', 'is_minor' => false]]],
        );

        $response->assertOk()->assertJsonPath('data.travelers.0.full_name', 'Ana Perez');
    }

    public function test_the_owner_cannot_edit_travelers_23_hours_before_departure(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(23));

        $this->actingAs($booking->user)
            ->putJson(
                $this->host($tenant)."/api/v1/bookings/{$booking->booking_number}/travelers",
                ['travelers' => [['full_name' => 'Ana Perez', 'is_minor' => false]]],
            )
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_TRAVELER_EDIT_WINDOW_CLOSED');

        $this->assertDatabaseCount('booking_travelers', 0);
    }

    public function test_a_departure_already_gone_keeps_the_manifest_frozen_for_the_owner(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->addDays(3));

        $this->actingAs($booking->user)
            ->putJson(
                $this->host($tenant)."/api/v1/bookings/{$booking->booking_number}/travelers",
                ['travelers' => [['full_name' => 'Ana Perez', 'is_minor' => false]]],
            )
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_TRAVELER_EDIT_WINDOW_CLOSED');
    }

    public function test_the_cutoff_comes_from_configuration_and_not_from_a_hardcoded_24(): void
    {
        config(['montree.passengers.traveler_edit_cutoff_hours' => 72]);

        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(48));

        $this->actingAs($booking->user)
            ->putJson(
                $this->host($tenant)."/api/v1/bookings/{$booking->booking_number}/travelers",
                ['travelers' => [['full_name' => 'Ana Perez', 'is_minor' => false]]],
            )
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_TRAVELER_EDIT_WINDOW_CLOSED');
    }

    public function test_the_panel_still_edits_a_passenger_one_hour_before_departure(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $departure->update(['starts_at' => Carbon::parse(self::DEPARTURE)]);
        $passenger = $this->passengerOn($this->bookingOn($departure));
        Tenant::forgetCurrent();

        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHour());

        $this->actingAs($admin)
            ->putJson($this->host($tenant)."/api/v1/admin/passengers/{$passenger->id}", [
                'full_name' => 'Maria Fernanda Rios',
            ])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Maria Fernanda Rios');

        $this->assertDatabaseHas('booking_travelers', [
            'id' => $passenger->id,
            'full_name' => 'Maria Fernanda Rios',
        ]);
    }

    public function test_the_booking_resource_publishes_the_open_window_and_its_deadline(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(25));

        $this->actingAs($booking->user)
            ->getJson($this->host($tenant)."/api/v1/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_travelers', true)
            ->assertJsonPath(
                'data.travelers_edit_deadline',
                Carbon::parse(self::DEPARTURE)->subHours(24)->toIso8601String(),
            );
    }

    public function test_the_booking_resource_reports_the_window_closed_inside_the_cutoff(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(23));

        $this->actingAs($booking->user)
            ->getJson($this->host($tenant)."/api/v1/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertJsonPath('data.can_edit_travelers', false);
    }

    /**
     * WHY (Fase 4): la pantalla del viajero es Inertia, no la API, y su prop
     * `booking` se armaba a mano sin los dos campos de D10. Sin ellos el
     * formulario no tiene forma de saber que la ventana cerró y solo se entera
     * por el 409 — exactamente lo que la decisión prohíbe.
     */
    public function test_the_booking_page_ships_the_edit_window_to_the_form(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(23));

        $this->actingAs($booking->user)
            ->get($this->host($tenant)."/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('booking.can_edit_travelers', false)
                ->where(
                    'booking.travelers_edit_deadline',
                    Carbon::parse(self::DEPARTURE)->subHours(24)->toIso8601String(),
                ));
    }

    /**
     * El formulario reemplaza al viajero entero en cada guardado. Si la pantalla
     * no puede releer la emergencia y la EPS que ya estaban guardadas, el
     * siguiente «Guardar» las borra.
     */
    public function test_the_booking_page_ships_the_health_block_of_saved_travelers(): void
    {
        [$tenant, $booking] = $this->bookingDepartingAt(self::DEPARTURE);
        $this->travelTo(Carbon::parse(self::DEPARTURE)->subHours(48));

        $booking->travelers()->create([
            'tenant_id' => $booking->tenant_id,
            'full_name' => 'Ana Perez',
            'is_minor' => false,
            'emergency_contact_name' => 'Julian Perez',
            'emergency_contact_relationship' => 'Hermano',
            'emergency_contact_phone' => '+57 311 222 3344',
            'eps' => 'sura',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);

        $this->actingAs($booking->user)
            ->get($this->host($tenant)."/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('booking.travelers.0.emergency_contact_name', 'Julian Perez')
                ->where('booking.travelers.0.emergency_contact_relationship', 'Hermano')
                ->where('booking.travelers.0.eps', 'sura')
                ->where('booking.travelers.0.medical_notes', 'Alergia a la penicilina.'));
    }

    /**
     * @return array{0: Tenant, 1: Booking}
     */
    private function bookingDepartingAt(string $startsAt): array
    {
        $tenant = $this->tenantAt();
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $departure->update(['starts_at' => Carbon::parse($startsAt)]);

        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $booking = Booking::factory()
            ->for($owner)
            ->for($departure->tour)
            ->for($departure)
            ->create([
                'status' => BookingStatus::Confirmed,
                'travelers_count' => 1,
                'adults_count' => 1,
                'minors_count' => 0,
            ]);

        Tenant::forgetCurrent();

        return [$tenant, $booking->setRelation('tourDate', $departure->fresh())];
    }
}
