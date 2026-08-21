<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\Eps;
use App\Enums\UserRole;
use App\Models\BookingTraveler;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Decisión 7: `sales` no ve el dato de salud. Las cinco superficies que hay que
 * tapar —campos, segmento, resumen, CSV y escritura— tienen aquí su prueba.
 */
final class MedicalPermissionTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_sales_does_not_receive_the_health_fields(): void
    {
        [$tenant, $tour] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $response = $this->actingAs($sales)->getJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers");

        $response->assertOk()
            ->assertJsonPath('meta.can_view_medical', false)
            ->assertJsonMissingPath('data.0.eps')
            ->assertJsonMissingPath('data.0.eps_label')
            ->assertJsonMissingPath('data.0.eps_other')
            ->assertJsonMissingPath('data.0.medical_notes');
    }

    public function test_sales_still_sees_the_emergency_contact(): void
    {
        [$tenant, $tour] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $this->actingAs($sales)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers")
            ->assertOk()
            ->assertJsonPath('data.0.emergency_contact_name', 'Julian Rios');
    }

    public function test_sales_is_rejected_on_the_observations_segment(): void
    {
        [$tenant, $tour] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $this->actingAs($sales)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers?segment=obs")
            ->assertForbidden()
            ->assertJsonPath('error_code', 'INSUFFICIENT_PERMISSION');
    }

    public function test_the_csv_of_sales_omits_the_two_medical_columns(): void
    {
        [$tenant, $tour] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $response = $this->actingAs($sales)->get($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers/export");

        $csv = $response->assertOk()->streamedContent();
        $this->assertStringNotContainsString('EPS', $csv);
        $this->assertStringNotContainsString('Observaciones', $csv);
        $this->assertStringNotContainsString('Alergia a la penicilina.', $csv);
    }

    public function test_sales_cannot_write_the_health_fields(): void
    {
        [$tenant, $tour, $passenger] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $response = $this->actingAs($sales)->putJson($this->host($tenant)."/api/v1/admin/passengers/{$passenger->id}", [
            'full_name' => 'Maria Fernanda Rios',
            'phone' => '+57 300 999 8877',
            'eps' => Eps::Sura->value,
            'eps_other' => null,
            'medical_notes' => 'Ninguna.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('booking_travelers', [
            'id' => $passenger->id,
            'phone' => '+57 300 999 8877',
            'eps' => Eps::Other->value,
            'eps_other' => 'Compensar',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);
    }

    public function test_the_summary_hides_the_observations_count_from_sales(): void
    {
        [$tenant, $tour] = $this->manifest();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        Tenant::forgetCurrent();

        $this->actingAs($sales)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers")
            ->assertOk()
            ->assertJsonMissingPath('meta.summary.with_notes')
            ->assertJsonPath('meta.summary.total_passengers', 1);
    }

    public function test_admin_receives_the_health_fields_and_the_observations_count(): void
    {
        [$tenant, $tour] = $this->manifest();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers")
            ->assertOk()
            ->assertJsonPath('meta.can_view_medical', true)
            ->assertJsonPath('data.0.eps', Eps::Other->value)
            ->assertJsonPath('data.0.eps_label', 'Otra')
            ->assertJsonPath('data.0.eps_other', 'Compensar')
            ->assertJsonPath('data.0.medical_notes', 'Alergia a la penicilina.')
            ->assertJsonPath('meta.summary.with_notes', 1);
    }

    public function test_the_csv_of_admin_carries_the_two_medical_columns(): void
    {
        [$tenant, $tour] = $this->manifest();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $csv = $this->actingAs($admin)
            ->get($this->host($tenant)."/api/v1/admin/tours/{$tour->id}/passengers/export")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('EPS,Observaciones', $csv);
        $this->assertStringContainsString('Alergia a la penicilina.', $csv);
    }

    public function test_the_guide_receives_the_health_fields_of_their_own_departure(): void
    {
        [$tenant, , , $departure] = $this->manifest();
        $guide = $departure->guide;
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson($this->host($tenant)."/api/v1/guide/tour-dates/{$departure->id}/passengers")
            ->assertOk()
            ->assertJsonPath('meta.can_view_medical', true)
            ->assertJsonPath('data.0.medical_notes', 'Alergia a la penicilina.');
    }

    /**
     * @return array{0: Tenant, 1: Tour, 2: BookingTraveler, 3: TourDate}
     */
    private function manifest(): array
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $tour = Tour::factory()->create();
        $departure = $this->departureFor($guide, $tour);
        $booking = $this->bookingOn($departure);

        $passenger = $this->passengerOn($booking, [
            'full_name' => 'Maria Fernanda Rios',
            'emergency_contact_name' => 'Julian Rios',
            'eps' => Eps::Other,
            'eps_other' => 'Compensar',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);

        return [$tenant, $tour, $passenger, $departure];
    }
}
