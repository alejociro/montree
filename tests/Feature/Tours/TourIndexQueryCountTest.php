<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Las cifras operativas del listado van por subconsultas correlacionadas: el
 * número de consultas no puede crecer con el número de tours de la página. Se
 * mide la misma página con 3 y con 9 productos —cada uno con salida, reserva y
 * pasajeros— y se exige el mismo conteo.
 */
final class TourIndexQueryCountTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_operational_summary_does_not_add_queries_per_tour(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->catalogOf(3);
        // WHY: la primera petición paga el catálogo de permisos y la resolución del
        // tenant; lo que se compara es el listado ya en caliente.
        $this->countQueriesOfTheIndex($admin, 3);
        $withThree = $this->countQueriesOfTheIndex($admin, 3);

        $this->catalogOf(27);
        $withFullPage = $this->countQueriesOfTheIndex($admin, 9);

        $this->assertSame(
            $withThree,
            $withFullPage,
            "El listado pasó de {$withThree} a {$withFullPage} consultas al llenar la página.",
        );
        $this->assertLessThan(20, $withFullPage, "El listado disparó {$withFullPage} consultas.");
    }

    private function countQueriesOfTheIndex(User $admin, int $expectedTours): int
    {
        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        DB::listen($listener);

        $this->actingAs($admin)
            ->get($this->host($this->tenant).'/admin/tours?sort=next_departure&direction=asc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tours.data', $expectedTours));

        DB::getEventDispatcher()->forget(QueryExecuted::class);

        return $queries;
    }

    private function catalogOf(int $tours): void
    {
        foreach (range(1, $tours) as $index) {
            $tour = Tour::factory()->create();
            $departure = TourDate::factory()->for($tour)->create([
                'starts_at' => now()->addDays($index + 1),
                'capacity' => 10,
                'booked_count' => 4,
            ]);

            Booking::factory()
                ->for(User::factory())
                ->for($tour)
                ->for($departure, 'tourDate')
                ->confirmed()
                ->create([
                    'travelers_count' => 2,
                    'adults_count' => 2,
                    'minors_count' => 0,
                    'subtotal' => '400.00',
                    'total_amount' => '400.00',
                    'paid_amount' => '100.00',
                ]);
        }
    }
}
