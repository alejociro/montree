<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * T9: las tres páginas Inertia de la vista paso a paso que reemplaza
 * `TourDateFormDialog` — producto preseleccionado, elegir producto desde el
 * tablero, y editar una salida existente.
 */
final class DepartureFormPagesTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();
        $this->admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_create_for_tour_preselects_the_product(): void
    {
        $tour = Tour::factory()->create();
        Route::factory()->for($tour)->create(['is_default' => true]);

        $response = $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/departures/create");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Departures/Form')
            ->where('mode', 'create')
            ->where('tourDate', null)
            ->where('preselectedTourId', $tour->id)
            ->has('tours', 1)
            ->where('tours.0.id', $tour->id)
            ->has('tours.0.departure_defaults')
            ->has('tours.0.routes', 1)
            ->has('departureOptions.guides')
            ->where('returnUrl', "/admin/tours/{$tour->id}/edit?tab=departures")
        );
    }

    public function test_create_without_a_tour_lists_every_product_of_the_tenant(): void
    {
        Tour::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->get($this->host($this->tenant).'/admin/departures/create');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Departures/Form')
            ->where('mode', 'create')
            ->where('preselectedTourId', null)
            ->has('tours', 3)
            ->where('returnUrl', '/admin/departures')
        );
    }

    public function test_create_honors_a_safe_return_query_parameter(): void
    {
        $tour = Tour::factory()->create();

        $response = $this->actingAs($this->admin)->get(
            $this->host($this->tenant).'/admin/departures/create?return=/admin/tours/'.$tour->id.'/edit',
        );

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('returnUrl', '/admin/tours/'.$tour->id.'/edit')
        );
    }

    public function test_create_ignores_an_external_return_url(): void
    {
        $response = $this->actingAs($this->admin)->get(
            $this->host($this->tenant).'/admin/departures/create?return=https://evil.test/phish',
        );

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('returnUrl', '/admin/departures')
        );
    }

    public function test_edit_carries_the_departure_and_its_tour(): void
    {
        $tour = Tour::factory()->create();
        $route = Route::factory()->for($tour)->create(['is_default' => true]);
        $departure = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'route_id' => $route->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get($this->host($this->tenant)."/admin/departures/{$departure->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Departures/Form')
            ->where('mode', 'edit')
            ->where('tourDate.id', $departure->id)
            ->where('preselectedTourId', $tour->id)
            ->has('tours', 1)
            ->where('returnUrl', '/admin/departures')
        );
    }

    public function test_a_guide_cannot_open_any_of_the_three_pages(): void
    {
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->create();
        $guide = $this->guideFor($this->tenant);

        $this->actingAs($guide)
            ->get($this->host($this->tenant).'/admin/departures/create')
            ->assertForbidden();

        $this->actingAs($guide)
            ->get($this->host($this->tenant)."/admin/tours/{$tour->id}/departures/create")
            ->assertForbidden();

        $this->actingAs($guide)
            ->get($this->host($this->tenant)."/admin/departures/{$departure->id}/edit")
            ->assertForbidden();
    }

    public function test_editing_a_departure_of_another_tenant_is_not_found(): void
    {
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->create();

        $other = $this->makeTenant(['slug' => 'other', 'domain' => 'other.montree.test']);
        $other->makeCurrent();
        $otherAdmin = $this->memberFor($other, UserRole::Admin);

        $this->actingAs($otherAdmin)
            ->get($this->host($other)."/admin/departures/{$departure->id}/edit")
            ->assertNotFound();
    }

    public function test_saving_from_the_page_redirects_to_the_return_url(): void
    {
        $tour = Tour::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            $this->host($this->tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'return' => "/admin/tours/{$tour->id}/edit?tab=departures",
            ],
        );

        $response->assertSessionHas('success');
        $response->assertRedirect("/admin/tours/{$tour->id}/edit?tab=departures");
        $this->assertDatabaseHas('tour_dates', ['tour_id' => $tour->id, 'capacity' => 12]);
    }

    public function test_saving_without_a_return_parameter_keeps_the_previous_behavior(): void
    {
        $tour = Tour::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            $this->host($this->tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
            ],
        );

        $response->assertSessionHas('success');
        $response->assertStatus(302);
    }

    public function test_saving_ignores_an_external_return_parameter(): void
    {
        $tour = Tour::factory()->create();

        $response = $this->actingAs($this->admin)->post(
            $this->host($this->tenant)."/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => now()->addDays(10)->toIso8601String(),
                'capacity' => 12,
                'return' => 'https://evil.test/phish',
            ],
        );

        $response->assertSessionHas('success');
        $this->assertNotSame('https://evil.test/phish', $response->headers->get('Location'));
    }
}
