<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * El historial de transacciones dentro de la planilla es dato del panel: viaja
 * con `payments.view` y no viaja sin él, así el guía no lo recibe escondido.
 */
final class ManifestPaymentsTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_guide_does_not_receive_the_transactions_of_the_booking(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);
        $booking = $this->bookingOn($departure);
        $this->passengerOn($booking, ['full_name' => 'Ana Gomez']);
        Payment::factory()->for($booking)->completed()->create(['reference' => 'MTR-77']);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertOk()
            ->assertJsonMissingPath('data.0.payments');
    }

    public function test_the_admin_receives_the_transactions_of_the_booking(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($admin);
        $booking = $this->bookingOn($departure);
        $this->passengerOn($booking, ['full_name' => 'Ana Gomez']);
        $payment = Payment::factory()->for($booking)->completed()->create(['reference' => 'MTR-77']);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertOk()
            ->assertJsonCount(1, 'data.0.payments')
            ->assertJsonPath('data.0.payments.0.id', $payment->id)
            ->assertJsonPath('data.0.payments.0.reference', 'MTR-77')
            ->assertJsonPath('data.0.payments.0.status', PaymentStatus::Completed->value);
    }

    public function test_the_admin_manifest_of_a_tour_also_ships_the_transactions(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $booking = $this->bookingOn($departure);
        $this->passengerOn($booking, ['full_name' => 'Ana Gomez']);
        Payment::factory()->for($booking)->completed()->create(['reference' => 'MTR-78']);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers")
            ->assertOk()
            ->assertJsonPath('data.0.payments.0.reference', 'MTR-78');
    }
}
