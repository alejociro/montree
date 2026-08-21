<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Las cifras operativas del listado van por subconsultas correlacionadas: el
 * número de consultas no puede crecer con el número de tours. Se mide el mismo
 * listado con 3 y con 30 tours —cada uno con salida, reserva y pasajeros— y se
 * exige el mismo conteo.
 */
final class TourIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($this->tenant)->create();
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_operational_summary_does_not_add_queries_per_tour(): void
    {
        $admin = $this->member(UserRole::Admin);

        $this->catalogOf(3);
        // WHY: la primera petición paga el catálogo de permisos y la resolución del
        // tenant; lo que se compara es el listado ya en caliente.
        $this->countQueriesOfTheIndex($admin, 3);
        $withThree = $this->countQueriesOfTheIndex($admin, 3);

        $this->catalogOf(27);
        $withThirty = $this->countQueriesOfTheIndex($admin, 30);

        $this->assertSame(
            $withThree,
            $withThirty,
            "El listado pasó de {$withThree} a {$withThirty} consultas al decuplicar el catálogo.",
        );
        $this->assertLessThan(10, $withThirty, "El listado disparó {$withThirty} consultas.");
    }

    private function countQueriesOfTheIndex(User $admin, int $expectedTours): int
    {
        $queries = 0;
        $listener = function () use (&$queries): void {
            $queries++;
        };

        DB::listen($listener);

        $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/tours?per_page=100&sort=next_departure&direction=asc')
            ->assertOk()
            ->assertJsonCount($expectedTours, 'data');

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

    private function member(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
