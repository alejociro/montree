<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class TourStopControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_creating_a_tour_with_stops_derives_pin_codes_from_the_order(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/tours', [
            'name' => 'Valle de Cocora',
            'description' => 'Caminata entre palmas de cera.',
            'base_price' => '150000.00',
            'currency' => 'COP',
            'duration_hours' => 10,
            'difficulty' => 'moderate',
            'default_capacity' => 12,
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'label' => 'Recogida', 'place' => 'Armenia', 'time' => '8:00 a. m.', 'latitude' => 4.5350, 'longitude' => -75.6813, 'itinerary_step' => 1],
                ['kind' => 'site', 'name' => 'Salento', 'latitude' => 4.6376, 'longitude' => -75.5706],
                ['kind' => 'site', 'name' => 'Bosque de palmas', 'latitude' => 4.6428, 'longitude' => -75.4790],
                ['kind' => 'drop', 'name' => 'Terminal de Armenia', 'label' => 'Regreso', 'latitude' => 4.5252, 'longitude' => -75.6812],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.stops.0.code', 'A');
        $response->assertJsonPath('data.stops.1.code', '1');
        $response->assertJsonPath('data.stops.2.code', '2');
        $response->assertJsonPath('data.stops.3.code', 'B');
        $response->assertJsonPath('data.stops.0.time', '8:00 a. m.');
        $response->assertJsonPath('data.stops.0.itinerary_step', 1);
        $this->assertDatabaseCount('tour_stops', 4);
    }

    public function test_updating_a_tour_replaces_its_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        TourStop::factory()->count(3)->for($tour)->sequence(
            ['position' => 1],
            ['position' => 2],
            ['position' => 3],
        )->create();

        $response = $this->actingAs($admin)->putJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Nueva recogida', 'latitude' => 4.5, 'longitude' => -75.6],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.stops');
        $this->assertDatabaseCount('tour_stops', 1);
    }

    public function test_a_tour_cannot_have_two_pickup_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create();

        $response = $this->actingAs($admin)->putJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Una', 'latitude' => 4.5, 'longitude' => -75.6],
                ['kind' => 'pickup', 'name' => 'Otra', 'latitude' => 4.6, 'longitude' => -75.7],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('stops');
    }

    public function test_stop_coordinates_are_required(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create();

        $response = $this->actingAs($admin)->putJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'site', 'name' => 'Sin coordenadas'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['stops.0.latitude', 'stops.0.longitude']);
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
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
