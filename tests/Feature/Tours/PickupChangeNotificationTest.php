<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\BookingStatus;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourStop;
use App\Models\User;
use App\Notifications\PickupPointChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Regla 6 del handoff: cambiar la parada de recogida de un tour con reservas
 * vivas avisa por correo a los pasajeros afectados. Afectado es quien todavía
 * puede aparecer en esa recogida: reserva viva y salida por venir.
 */
final class PickupChangeNotificationTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_moving_the_pickup_notifies_active_bookings(): void
    {
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $traveler = $this->bookingFor($tour)->user;

        Notification::fake();

        $this->actingAs($admin)->putJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Terminal del Café', 'latitude' => 4.5352, 'longitude' => -75.6814],
            ],
        ])->assertOk();

        Notification::assertSentTo(
            $traveler,
            PickupPointChangedNotification::class,
            function (PickupPointChangedNotification $notification): bool {
                return str_contains((string) $notification->previousPickup, 'Plaza de Bolívar')
                    && str_contains((string) $notification->currentPickup, 'Terminal del Café');
            },
        );
    }

    public function test_the_same_pickup_notifies_nobody(): void
    {
        // El sync reescribe las paradas enteras en cada guardado: si eso
        // contara como cambio, editar el precio mandaría correos.
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $this->bookingFor($tour);

        Notification::fake();

        $this->actingAs($admin)->putJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'place' => 'Armenia', 'time' => '8:00 a. m.', 'latitude' => 4.5350, 'longitude' => -75.6813],
            ],
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_changing_another_stop_notifies_nobody(): void
    {
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $this->bookingFor($tour);

        Notification::fake();

        $this->actingAs($admin)->putJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'place' => 'Armenia', 'time' => '8:00 a. m.', 'latitude' => 4.5350, 'longitude' => -75.6813],
                ['kind' => 'site', 'name' => 'Bosque de palmas', 'latitude' => 4.6428, 'longitude' => -75.4790],
            ],
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_removing_the_pickup_also_notifies(): void
    {
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $traveler = $this->bookingFor($tour)->user;

        Notification::fake();

        $this->actingAs($admin)->putJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'site', 'name' => 'Bosque de palmas', 'latitude' => 4.6428, 'longitude' => -75.4790],
            ],
        ])->assertOk();

        Notification::assertSentTo(
            $traveler,
            PickupPointChangedNotification::class,
            fn (PickupPointChangedNotification $notification): bool => $notification->currentPickup === null,
        );
    }

    public function test_cancelled_bookings_and_past_departures_are_left_alone(): void
    {
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $cancelled = $this->bookingFor($tour, BookingStatus::Cancelled)->user;
        $past = $this->bookingFor($tour, BookingStatus::Confirmed, '2026-08-01 07:00:00')->user;

        Notification::fake();

        $this->actingAs($admin)->putJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Terminal del Café', 'latitude' => 4.5352, 'longitude' => -75.6814],
            ],
        ])->assertOk();

        Notification::assertNotSentTo($cancelled, PickupPointChangedNotification::class);
        Notification::assertNotSentTo($past, PickupPointChangedNotification::class);
    }

    public function test_the_tour_response_counts_who_would_be_notified(): void
    {
        // El aviso de la UI y el envío real cuentan lo mismo: si no, la pantalla
        // prometería un número que después no se cumple.
        [$tenant, $admin, $tour] = $this->scenario();
        $this->pickup($tour, 'Plaza de Bolívar');
        $this->bookingFor($tour, BookingStatus::Confirmed, null, 3);
        $this->bookingFor($tour, BookingStatus::PendingPayment, null, 2);
        $this->bookingFor($tour, BookingStatus::Cancelled, null, 5);

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
        );

        $response->assertOk();
        $response->assertJsonPath('data.pickup_change_impact.bookings', 2);
        $response->assertJsonPath('data.pickup_change_impact.passengers', 5);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Tour}
     */
    private function scenario(): array
    {
        Carbon::setTestNow('2026-09-01 08:00:00');

        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create([
            'duration_hours' => 8,
            'name' => 'Valle de Cocora',
            'default_guide_id' => $this->guideFor($tenant)->id,
        ]);

        return [$tenant, $admin, $tour];
    }

    private function pickup(Tour $tour, string $name): TourStop
    {
        return TourStop::factory()->for($tour)->create([
            'kind' => TourStopKind::Pickup,
            'code' => 'A',
            'position' => 1,
            'name' => $name,
            'place' => 'Armenia',
            'time_label' => '8:00 a. m.',
            'latitude' => 4.5350,
            'longitude' => -75.6813,
        ]);
    }

    private function bookingFor(
        Tour $tour,
        BookingStatus $status = BookingStatus::Confirmed,
        ?string $startsAt = null,
        int $travelers = 1,
    ): Booking {
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor(Tenant::current())->id,
            'starts_at' => $startsAt ?? '2026-09-20 07:00:00',
        ]);

        return Booking::factory()->create([
            'tour_id' => $tour->id,
            'tour_date_id' => $departure->id,
            'status' => $status,
            'travelers_count' => $travelers,
        ]);
    }
}
