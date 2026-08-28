<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\TourDate;

use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tablero de salidas: bandejas, buscador, cifras de cabecera y el camino de
 * vuelta de una salida inhabilitada.
 */
class DepartureBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_scope_splits_upcoming_past_and_disabled(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();

        $upcoming = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(6),
            'status' => TourDateStatus::Open,
        ]);
        $past = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subDays(4)->addHours(6),
            'status' => TourDateStatus::Open,
        ]);
        $disabled = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(6),
            'status' => TourDateStatus::Cancelled,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->assertSingle($admin, 'scope=upcoming', $upcoming->id);
        $this->assertSingle($admin, 'scope=past', $past->id);
        $this->assertSingle($admin, 'scope=disabled', $disabled->id);

        $all = $this->actingAs($admin)->getJson($this->url('scope=all'));
        $all->assertOk();
        $all->assertJsonCount(3, 'data');
        $all->assertJsonPath('counts.upcoming', 1);
        $all->assertJsonPath('counts.past', 1);
        $all->assertJsonPath('counts.disabled', 1);
        $all->assertJsonPath('counts.all', 3);
    }

    public function test_search_matches_tour_name_and_derived_code(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $cocora = Tour::factory()->create(['name' => 'Cocora Trek']);
        $andes = Tour::factory()->create(['name' => 'Andes Ride']);

        $target = TourDate::factory()->for($cocora)->create([
            'starts_at' => now()->addDays(3)->setTime(7, 15),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($andes)->create([
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->assertSingle($admin, 'scope=all&search=Cocora', $target->id);
        $this->assertSingle($admin, 'scope=all&search='.$target->code(), $target->id);
    }

    public function test_stats_ignore_the_active_tray_and_search(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['name' => 'Cocora Trek']);

        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
            'booked_count' => 4,
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(8),
            'capacity' => 6,
            'booked_count' => 6,
            'status' => TourDateStatus::Full,
        ]);

        $admin = $this->memberFor($tenant, UserRole::Admin);

        // La bandeja «inhabilitadas» no devuelve ninguna fila; los KPIs siguen
        // describiendo la operación entera.
        $response = $this->actingAs($admin)->getJson($this->url('scope=disabled'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('stats.active', 2);
        $response->assertJsonPath('stats.seats_left', 6);
        $response->assertJsonPath('stats.travellers', 10);
    }

    public function test_restore_brings_a_disabled_departure_back(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'capacity' => 10,
            'booked_count' => 2,
            'status' => TourDateStatus::Cancelled,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            'http://demo.montree.test/api/v1/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', TourDateStatus::Open->value);
        $this->assertSame(TourDateStatus::Open, $date->fresh()?->status);
    }

    public function test_restore_returns_full_when_there_is_no_room_left(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'capacity' => 8,
            'booked_count' => 8,
            'status' => TourDateStatus::Cancelled,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            'http://demo.montree.test/api/v1/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', TourDateStatus::Full->value);
    }

    public function test_restore_rejects_a_departure_that_is_not_disabled(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'status' => TourDateStatus::Open,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            'http://demo.montree.test/api/v1/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_DATE_NOT_CANCELLED');
    }

    public function test_restore_forbidden_for_a_guide(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(4),
            'status' => TourDateStatus::Cancelled,
        ]);
        $guide = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($guide)->patchJson(
            'http://demo.montree.test/api/v1/admin/tour-dates/'.$date->id.'/restore',
        );

        $response->assertStatus(403);
    }

    private function assertSingle(User $admin, string $query, int $expectedId): void
    {
        $response = $this->actingAs($admin)->getJson($this->url($query));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $expectedId);
    }

    private function url(string $query): string
    {
        return 'http://demo.montree.test/api/v1/admin/tour-dates?'.$query;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($tenant->id);
        Role::findOrCreate($role->value, 'web');
        $user->assignRole($role->value);

        return $user;
    }
}
