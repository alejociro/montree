<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Segmento + búsqueda + salida se combinan, y el pie de la tabla se recalcula
 * sobre el resultado, no sobre el total del tour.
 */
final class PassengerManifestFilterTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_summary_is_computed_over_the_filtered_result(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));

        $this->passengerOn($this->bookingOn($departure, 1, '400000.00', '400000.00'), ['full_name' => 'Ana Paid']);
        $this->passengerOn($this->bookingOn($departure, 1, '300000.00', '100000.00'), ['full_name' => 'Beto Due']);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers?segment=due",
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Beto Due')
            ->assertJsonPath('meta.summary.total_passengers', 1)
            ->assertJsonPath('meta.summary.with_due', 1)
            ->assertJsonPath('meta.summary.paid', 0)
            ->assertJsonPath('meta.summary.total_due_amount', '200000.00')
            ->assertJsonPath('meta.summary.currency', 'COP');
    }

    public function test_the_search_matches_the_document_number(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $booking = $this->bookingOn($departure, 2);

        $this->passengerOn($booking, ['full_name' => 'Ana Gomez', 'document_number' => '1017234567']);
        $this->passengerOn($booking, ['full_name' => 'Beto Ruiz', 'document_number' => '9999999']);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers?q=1017234567")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Ana Gomez');
    }

    public function test_a_booking_without_travelers_becomes_a_placeholder_row(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $booking = $this->bookingOn($departure);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers",
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', null)
            ->assertJsonPath('data.0.document_number', null)
            ->assertJsonPath('data.0.full_name', $booking->user->name)
            ->assertJsonPath('data.0.booking_number', $booking->booking_number);
    }

    public function test_no_match_returns_an_empty_manifest(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $this->passengerOn($this->bookingOn($departure), ['full_name' => 'Ana Gomez']);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers?q=zzzz")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.summary.total_passengers', 0)
            ->assertJsonPath('meta.summary.total_due_amount', '0.00');
    }

    public function test_a_departure_of_another_tour_is_rejected(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $departure = $this->departureFor($guide);
        $foreign = $this->departureFor($guide, Tour::factory()->create());
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers?tour_date_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('tour_date_id');
    }

    public function test_the_manifest_of_another_tenant_is_not_reachable(): void
    {
        $other = $this->tenantAt('otra');
        $departure = $this->departureFor($this->memberOf($other, UserRole::Guide));
        $this->passengerOn($this->bookingOn($departure), ['full_name' => 'Ajena Perez']);

        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers")
            ->assertNotFound();
    }
}
