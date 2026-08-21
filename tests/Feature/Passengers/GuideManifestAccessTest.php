<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * El alcance del guía es la pertenencia: su salida sí, la del compañero no.
 */
final class GuideManifestAccessTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_assigned_guide_sees_the_manifest(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);
        $this->passengerOn($this->bookingOn($departure), ['full_name' => 'Ana Gomez']);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Ana Gomez')
            ->assertJsonCount(1, 'meta.departures')
            ->assertJsonPath('meta.departures.0.guide.id', $guide->id);
    }

    public function test_a_guide_who_is_not_assigned_is_rejected(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $other = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);
        Tenant::forgetCurrent();

        $this->actingAs($other)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertForbidden();
    }

    public function test_the_manifest_of_the_guide_leaves_out_the_bookings_that_are_not_confirmed(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);

        $pending = $this->bookingOn($departure);
        $pending->update(['status' => BookingStatus::PendingPayment]);
        $this->passengerOn($pending, ['full_name' => 'Zoe Pending']);
        $this->passengerOn($this->bookingOn($departure), ['full_name' => 'Ana Confirmed']);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Ana Confirmed');
    }

    public function test_the_guide_cannot_force_the_statuses_of_the_panel(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);

        $pending = $this->bookingOn($departure);
        $pending->update(['status' => BookingStatus::PendingPayment]);
        $this->passengerOn($pending, ['full_name' => 'Zoe Pending']);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers?status[]=pending_payment")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_departure_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->tenantAt('otra');
        $foreignDeparture = $this->departureFor($this->memberOf($other, UserRole::Guide));

        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$foreignDeparture->id}/passengers")
            ->assertNotFound();
    }
}
