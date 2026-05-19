<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

use App\Enums\BookingStatus;
use App\Enums\TenantPlan;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TourControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_can_list_tours_for_current_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->count(3)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data' => [['id', 'slug', 'name', 'status', 'base_price']], 'meta', 'links']);
    }

    public function test_index_filters_by_status_search_and_category(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $category = Category::factory()->create(['name' => 'Senderismo', 'slug' => 'senderismo']);
        Tour::factory()->create(['name' => 'Camino de Cocora', 'status' => TourStatus::Active, 'category_id' => $category->id]);
        Tour::factory()->create(['name' => 'Buceo Tayrona', 'status' => TourStatus::Draft]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $byStatus = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours?status=active');
        $byStatus->assertOk();
        $byStatus->assertJsonCount(1, 'data');
        $byStatus->assertJsonPath('data.0.name', 'Camino de Cocora');

        $bySearch = $this->actingAs($admin)->getJson('http://demo.montree.test/api/v1/admin/tours?search=Buceo');
        $bySearch->assertJsonCount(1, 'data');
        $bySearch->assertJsonPath('data.0.name', 'Buceo Tayrona');

        $byCategory = $this->actingAs($admin)->getJson("http://demo.montree.test/api/v1/admin/tours?category_id={$category->id}");
        $byCategory->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_tour_in_draft_status(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $category = Category::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/tours', [
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
            'includes' => ['Guía', 'Snacks'],
            'requirements' => ['Calzado adecuado'],
            'itinerary' => [
                ['step_number' => 1, 'title' => 'Salida', 'description' => 'Encuentro en la plaza', 'duration_label' => '30 min'],
                ['step_number' => 2, 'title' => 'Caminata', 'description' => 'Subida al mirador', 'duration_label' => '4 h'],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Sendero del Quindío');
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.slug', 'sendero-del-quindio');
        $this->assertDatabaseHas('tours', ['slug' => 'sendero-del-quindio', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('tour_itineraries', 2);
    }

    public function test_store_validates_required_fields(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/tours', [
            'name' => '',
            'currency' => 'INVALID',
            'base_price' => -10,
            'difficulty' => 'lunar',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'description', 'currency', 'difficulty', 'duration_hours', 'default_capacity', 'base_price']);
    }

    public function test_store_fails_when_plan_limit_reached(): void
    {
        $tenant = $this->makeTenant(['plan' => TenantPlan::Basic, 'plan_limits' => ['max_tours' => 1]]);
        $tenant->makeCurrent();
        Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/tours', $this->validPayload());

        $response->assertStatus(403);
        $response->assertJsonPath('error_code', 'PLAN_LIMIT_TOURS_REACHED');
    }

    public function test_store_auto_generates_unique_slug_on_collision(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Tour::factory()->create(['slug' => 'cocora-trail']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/tours', $this->validPayload(['name' => 'Cocora Trail']));

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'cocora-trail-2');
    }

    public function test_update_replaces_itinerary(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['name' => 'Original Tour']);
        $tour->itineraries()->create(['step_number' => 1, 'title' => 'Old step', 'description' => 'old']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}", [
            'name' => 'Updated Tour',
            'description' => 'New description.',
            'base_price' => '200000.00',
            'currency' => 'COP',
            'duration_hours' => 5,
            'difficulty' => 'easy',
            'default_capacity' => 8,
            'itinerary' => [
                ['step_number' => 1, 'title' => 'New step', 'description' => 'fresh'],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Tour');
        $this->assertSame(1, $tour->itineraries()->count());
        $this->assertSame('New step', $tour->itineraries()->first()?->title);
    }

    public function test_destroy_blocked_when_tour_has_active_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create();
        $user = User::factory()->create();
        Booking::factory()->for($user)->for($tour)->for($date, 'tourDate')->create([
            'status' => BookingStatus::Confirmed,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}");

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_HAS_ACTIVE_BOOKINGS');
        $this->assertNull($tour->fresh()?->deleted_at);
    }

    public function test_destroy_soft_deletes_tour_without_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('tours', ['id' => $tour->id]);
    }

    public function test_operator_cannot_delete_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $response = $this->actingAs($operator)->deleteJson("http://demo.montree.test/api/v1/admin/tours/{$tour->id}");

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_prevents_seeing_other_tenant_tours(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        Tour::factory()->create(['name' => 'Tour A']);
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create(['name' => 'Tour B']);

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->getJson('http://alpha.montree.test/api/v1/admin/tours');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Tour A');

        $detail = $this->actingAs($adminA)->getJson("http://alpha.montree.test/api/v1/admin/tours/{$tourB->id}");
        $detail->assertStatus(404);
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
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    /**
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
        ], $overrides);
    }
}
