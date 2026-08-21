<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\Eps;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * CSV de la planilla: encabezados, BOM y el resultado filtrado completo.
 */
final class PassengerExportTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    private const HEADERS = [
        'Nombre completo', 'Tipo de documento', 'Documento', 'Email', 'Teléfono',
        'Contacto de emergencia', 'Parentesco', 'Teléfono de emergencia',
        'EPS', 'Observaciones', 'Salida', 'Reserva', 'Valor', 'Abonado', 'Saldo', 'Estado',
    ];

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_csv_carries_the_bom_and_the_agreed_headers(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $this->passengerOn($this->bookingOn($departure), [
            'full_name' => 'Ana Gomez',
            'eps' => Eps::Sura,
            'eps_other' => null,
        ]);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->get(
            $this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers/export",
        );

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame(self::HEADERS, str_getcsv(strtok(substr($csv, 3), "\n"), ',', '"', '\\'));
        $this->assertStringContainsString('Ana Gomez', $csv);
        $this->assertStringContainsString('Sura', $csv);
    }

    public function test_the_filename_names_the_tour(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $slug = $departure->tour->slug;
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->get($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers/export")
            ->assertHeader(
                'Content-Disposition',
                sprintf('attachment; filename="pasajeros-%s-%s.csv"', $slug, now()->toDateString()),
            );
    }

    public function test_the_csv_exports_the_filtered_result_and_ignores_the_page_size(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $booking = $this->bookingOn($departure, 2);
        $this->passengerOn($booking, ['full_name' => 'Ana Gomez']);
        $this->passengerOn($booking, ['full_name' => 'Beto Ruiz']);
        Tenant::forgetCurrent();

        $csv = $this->actingAs($admin)
            ->get($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers/export?per_page=10&q=Ana")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Ana Gomez', $csv);
        $this->assertStringNotContainsString('Beto Ruiz', $csv);
    }

    public function test_the_guide_exports_their_own_departure_and_no_other(): void
    {
        $tenant = $this->tenantAt();
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $colleague = $this->memberOf($tenant, UserRole::Guide);
        $mine = $this->departureFor($guide);
        $theirs = $this->departureFor($colleague);
        $this->passengerOn($this->bookingOn($mine), ['full_name' => 'Ana Gomez']);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->get($this->host($tenant)."/api/v1/guide/tour-dates/{$mine->id}/passengers/export")
            ->assertOk();

        $this->actingAs($guide)
            ->get($this->host($tenant)."/api/v1/guide/tour-dates/{$theirs->id}/passengers/export")
            ->assertForbidden();
    }
}
