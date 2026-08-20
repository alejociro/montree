<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\Eps;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Ningún dato del pasajero sale por la ficha pública del tour: ni salud, ni
 * documento, ni contacto de emergencia.
 */
final class PassengerPrivacyTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_public_tour_response_carries_no_passenger_data(): void
    {
        $tenant = $this->tenantAt();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide), $tour);
        $this->passengerOn($this->bookingOn($departure), [
            'full_name' => 'Maria Fernanda Rios',
            'document_number' => '1017234567',
            'emergency_contact_name' => 'Julian Rios',
            'eps' => Eps::Other,
            'eps_other' => 'Compensar',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);
        Tenant::forgetCurrent();

        $body = $this->getJson($this->host($tenant)."/api/v1/tours/{$tour->slug}")
            ->assertOk()
            ->getContent();

        foreach (['Maria Fernanda Rios', '1017234567', 'Julian Rios', 'Compensar', 'Alergia a la penicilina.', 'medical_notes', 'eps_other'] as $needle) {
            $this->assertStringNotContainsString($needle, (string) $body);
        }
    }

    public function test_a_traveler_cannot_reach_the_manifest_of_the_panel(): void
    {
        $tenant = $this->tenantAt();
        $customer = $this->memberOf($tenant, UserRole::Customer);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        Tenant::forgetCurrent();

        $this->actingAs($customer)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers")
            ->assertForbidden();
    }

    public function test_a_guest_cannot_reach_the_manifest(): void
    {
        $tenant = $this->tenantAt();
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        Tenant::forgetCurrent();

        $this->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers")
            ->assertUnauthorized();
    }
}
