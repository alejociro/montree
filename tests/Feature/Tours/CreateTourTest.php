<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TenantPlan;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CreateTourTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_creates_a_tour_in_draft_status(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $category = Category::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post($this->host($tenant).'/admin/tours', [
            'name' => 'Sendero del Quindío',
            'short_description' => 'Caminata tranquila',
            'description' => 'Recorrido por el valle del Cocora.',
            'category_id' => $category->id,
            'base_price' => '150000.00',
            'currency' => 'COP',
            'duration_hours' => 6,
            'difficulty' => 'moderate',
            'default_capacity' => 12,
            'meeting_point' => 'Plaza Cocora',
            'meeting_latitude' => 4.6371,
            'meeting_longitude' => -75.5096,
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza Cocora', 'latitude' => 4.6371, 'longitude' => -75.5096],
                ['kind' => 'site', 'name' => 'Mirador', 'latitude' => 4.6428, 'longitude' => -75.4790],
                ['kind' => 'drop', 'name' => 'Plaza Cocora', 'latitude' => 4.6371, 'longitude' => -75.5096],
            ],
            'includes' => ['Guía', 'Snacks'],
            'requirements' => ['Calzado adecuado'],
            'itinerary' => [
                ['step_number' => 1, 'title' => 'Salida', 'description' => 'Encuentro en la plaza', 'duration_label' => '30 min'],
                ['step_number' => 2, 'title' => 'Caminata', 'description' => 'Subida al mirador', 'duration_label' => '4 h'],
            ],
        ]);

        $created = Tour::query()->where('slug', 'sendero-del-quindio')->sole();
        $response->assertRedirect($this->host($tenant).'/admin/tours/'.$created->id.'/edit');
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tours', [
            'id' => $created->id,
            'name' => 'Sendero del Quindío',
            'status' => TourStatus::Draft->value,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseCount('tour_itineraries', 2);
    }

    public function test_store_validates_required_fields(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post($this->host($tenant).'/admin/tours', [
            'name' => '',
            'currency' => 'INVALID',
            'base_price' => -10,
            'difficulty' => 'lunar',
        ]);

        $response->assertSessionHasErrors(['name', 'description', 'currency', 'difficulty', 'duration_hours', 'default_capacity', 'base_price']);
    }

    public function test_store_requires_meeting_point_destination_and_return(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/tours',
            $this->validPayload([
                'meeting_point' => '',
                'meeting_latitude' => null,
                'meeting_longitude' => null,
                'stops' => [
                    ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'latitude' => 4.5350, 'longitude' => -75.6813],
                ],
            ]),
        );

        $response->assertSessionHasErrors([
            'meeting_point',
            'meeting_latitude',
            'meeting_longitude',
            'stops',
        ]);
    }

    /**
     * El límite de plan ya no es un 403 JSON: vuelve al formulario como error
     * de la clave `plan`, con el input preservado.
     */
    public function test_store_fails_when_plan_limit_reached(): void
    {
        $tenant = $this->makeTenant(['plan' => TenantPlan::Basic, 'plan_limits' => ['max_tours' => 1]]);
        $tenant->makeCurrent();
        Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post($this->host($tenant).'/admin/tours', $this->validPayload());

        $response->assertSessionHasErrors('plan');
        $this->assertSame(1, Tour::query()->count());
    }

    public function test_store_auto_generates_unique_slug_on_collision(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->create(['slug' => 'cocora-trail']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/tours',
            $this->validPayload(['name' => 'Cocora Trail']),
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tours', ['slug' => 'cocora-trail-2', 'tenant_id' => $tenant->id]);
    }

    public function test_a_sales_member_cannot_create_a_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->post($this->host($tenant).'/admin/tours', $this->validPayload())
            ->assertForbidden();
    }

    public function test_the_new_tour_belongs_to_the_acting_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $this->actingAs($adminA)
            ->post($this->host($tenantA).'/admin/tours', $this->validPayload(['name' => 'Solo de Alpha']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tours', ['name' => 'Solo de Alpha', 'tenant_id' => $tenantA->id]);
        $this->assertDatabaseMissing('tours', ['name' => 'Solo de Alpha', 'tenant_id' => $tenantB->id]);
    }

    /**
     * Payload mínimo que HOY acepta la creación: además de los datos
     * comerciales, un tour no puede nacer sin punto de encuentro, sin destino
     * y sin regreso.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tour Demo',
            'description' => 'Descripción demo',
            'base_price' => '100000.00',
            'currency' => 'COP',
            'duration_hours' => 4,
            'difficulty' => 'easy',
            'default_capacity' => 10,
            'meeting_point' => 'Plaza de Bolívar, Armenia',
            'meeting_latitude' => 4.5350,
            'meeting_longitude' => -75.6813,
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza de Bolívar', 'latitude' => 4.5350, 'longitude' => -75.6813],
                ['kind' => 'site', 'name' => 'Valle de Cocora', 'latitude' => 4.6376, 'longitude' => -75.5706],
                ['kind' => 'drop', 'name' => 'Terminal de Armenia', 'latitude' => 4.5252, 'longitude' => -75.6812],
            ],
        ], $overrides);
    }
}
