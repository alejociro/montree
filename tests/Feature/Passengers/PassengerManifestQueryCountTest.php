<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * La planilla de 50 reservas no puede costar 50 consultas: cada fila necesita
 * su reserva, su titular y su salida, y las tres van por eager loading.
 */
final class PassengerManifestQueryCountTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_manifest_of_fifty_bookings_stays_within_a_bounded_number_of_queries(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));

        foreach (range(1, 50) as $index) {
            $this->passengerOn($this->bookingOn($departure), ['full_name' => "Pasajero {$index}"]);
        }

        Tenant::forgetCurrent();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers?per_page=100")
            ->assertOk()
            ->assertJsonCount(50, 'data');

        $this->assertLessThan(20, $queries, "La planilla disparó {$queries} consultas.");
    }
}
