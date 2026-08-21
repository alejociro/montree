<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\BookingStatus;
use App\Enums\Eps;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Alta y edición de pasajeros desde el panel.
 */
final class PassengerWriteTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_panel_adds_a_passenger_to_an_existing_booking(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/passengers", [
            'full_name' => 'Maria Fernanda Rios',
            'is_minor' => false,
            'document_type' => 'cc',
            'document_number' => '1017234567',
            'eps' => 'other',
            'eps_other' => 'Compensar',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.full_name', 'Maria Fernanda Rios')
            ->assertJsonPath('data.document_type_label', 'Cédula de ciudadanía')
            ->assertJsonPath('data.eps_other', 'Compensar')
            ->assertJsonPath('data.payment.share_amount', '200000.00');

        $this->assertDatabaseHas('booking_travelers', [
            'booking_id' => $booking->id,
            'document_number' => '1017234567',
        ]);
    }

    public function test_a_full_booking_does_not_take_one_more_passenger(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 1);
        $this->passengerOn($booking);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/passengers", [
                'full_name' => 'Uno De Mas',
                'is_minor' => false,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_TRAVELERS_COMPLETE');
    }

    public function test_a_cancelled_booking_does_not_take_a_passenger(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2);
        $booking->update(['status' => BookingStatus::Cancelled]);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/passengers", [
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_TRAVELERS_LOCKED');
    }

    public function test_the_other_eps_demands_its_free_text(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/passengers", [
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'other',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('eps_other');
    }

    public function test_the_panel_edits_a_passenger_and_drops_the_free_text_of_another_eps(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $passenger = $this->passengerOn(
            $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide))),
            ['eps' => Eps::Other, 'eps_other' => 'Compensar'],
        );
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->putJson($this->host($tenant)."/api/v1/admin/passengers/{$passenger->id}", [
                'full_name' => 'Maria Fernanda Rios',
                'eps' => 'sura',
                'eps_other' => 'Compensar',
            ])
            ->assertOk()
            ->assertJsonPath('data.eps', 'sura')
            ->assertJsonPath('data.eps_other', null);

        $this->assertDatabaseHas('booking_travelers', [
            'id' => $passenger->id,
            'eps' => Eps::Sura->value,
            'eps_other' => null,
        ]);
    }

    public function test_a_passenger_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->tenantAt('otra');
        $foreign = $this->passengerOn($this->bookingOn($this->departureFor($this->memberOf($other, UserRole::Guide))));

        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->putJson($this->host($tenant)."/api/v1/admin/passengers/{$foreign->id}", ['full_name' => 'Ajena Perez'])
            ->assertNotFound();
    }
}
