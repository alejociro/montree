<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourStop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class TourStopsTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

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

        $response = $this->actingAs($admin)->post($this->host($tenant).'/admin/tours', [
            'name' => 'Valle de Cocora',
            'description' => 'Caminata entre palmas de cera.',
            'base_price' => '150000.00',
            'duration_hours' => 10,
            'difficulty' => 'moderate',
            'default_capacity' => 12,
            'meeting_point' => 'Plaza de Bolívar, Armenia',
            'meeting_latitude' => 4.5350,
            'meeting_longitude' => -75.6813,
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'label' => 'Recogida', 'place' => 'Armenia', 'time' => '8:00 a. m.', 'latitude' => 4.5350, 'longitude' => -75.6813, 'itinerary_step' => 1],
                ['kind' => 'site', 'name' => 'Salento', 'latitude' => 4.6376, 'longitude' => -75.5706],
                ['kind' => 'site', 'name' => 'Bosque de palmas', 'latitude' => 4.6428, 'longitude' => -75.4790],
                ['kind' => 'drop', 'name' => 'Terminal de Armenia', 'label' => 'Regreso', 'latitude' => 4.5252, 'longitude' => -75.6812],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tour_stops', 4);

        $tour = Tour::query()->where('slug', 'valle-de-cocora')->sole();
        $this->assertSame(['A', '1', '2', 'B'], $tour->stops()->orderBy('position')->pluck('code')->all());
        $pickup = $tour->stops()->orderBy('position')->firstOrFail();
        $this->assertSame('8:00 a. m.', $pickup->time_label);
        $this->assertSame(1, $pickup->itinerary_step);

        $page = $this->actingAs($admin)->get($this->host($tenant).'/admin/tours/'.$tour->id);
        $page->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('tour.stops.0.code', 'A')
            ->where('tour.stops.3.code', 'B')
        );
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

        $response = $this->actingAs($admin)->put($this->host($tenant)."/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Nueva recogida', 'latitude' => 4.5, 'longitude' => -75.6],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseCount('tour_stops', 1);
        $this->assertSame('Nueva recogida', $tour->stops()->firstOrFail()->name);
    }

    public function test_a_tour_cannot_have_two_pickup_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create();

        $this->actingAs($admin)->put($this->host($tenant)."/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Una', 'latitude' => 4.5, 'longitude' => -75.6],
                ['kind' => 'pickup', 'name' => 'Otra', 'latitude' => 4.6, 'longitude' => -75.7],
            ],
        ])->assertSessionHasErrors('stops');
    }

    public function test_stop_coordinates_are_required(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $tour = Tour::factory()->create();

        $this->actingAs($admin)->put($this->host($tenant)."/admin/tours/{$tour->id}", [
            'stops' => [
                ['kind' => 'site', 'name' => 'Sin coordenadas'],
            ],
        ])->assertSessionHasErrors(['stops.0.latitude', 'stops.0.longitude']);
    }
}
