<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Logistics;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LogisticsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_filters_routes_by_search(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Route::factory()->create(['name' => 'Ruta El Mirador']);
        Route::factory()->create(['name' => 'Sendero del Río']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/routes?search=Mirador',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ruta El Mirador');
        $response->assertJsonPath('data.0.tour_dates_count', 0);
    }

    public function test_admin_creates_route(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/routes',
            ['name' => 'Ruta Cocora', 'distance_km' => '8.5', 'duration_hours' => '5'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Ruta Cocora');
        $this->assertDatabaseHas('routes', ['name' => 'Ruta Cocora', 'tenant_id' => $tenant->id]);
    }

    public function test_creating_route_without_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/routes',
            ['distance_km' => '8.5'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_admin_updates_provider(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $provider = Provider::factory()->create(['name' => 'Viejo Nombre']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/providers/{$provider->id}",
            ['name' => 'Transportes Andinos'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Transportes Andinos');
    }

    public function test_deleting_route_in_use_returns_409(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['route_id' => $route->id]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/routes/{$route->id}",
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'RESOURCE_IN_USE');
        $this->assertDatabaseHas('routes', ['id' => $route->id]);
    }

    public function test_deleting_unused_hotel_succeeds(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $hotel = Hotel::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/hotels/{$hotel->id}",
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
    }

    public function test_admin_creates_hotel(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/hotels',
            ['name' => 'Ecohotel La Montaña', 'contact_email' => 'hola@montana.test'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Ecohotel La Montaña');
    }

    public function test_tenant_isolation_updating_other_tenant_route_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $routeB = Route::factory()->create();

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->putJson(
            "http://alpha.montree.test/api/v1/admin/routes/{$routeB->id}",
            ['name' => 'Hackeada'],
        );

        $response->assertStatus(404);
    }

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
