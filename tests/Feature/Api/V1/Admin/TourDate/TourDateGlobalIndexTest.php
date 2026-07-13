<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\TourDate;

use App\Enums\TourDateDisplayStatus;
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

class TourDateGlobalIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_lists_departures_across_products_with_tour_embedded(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tourA = Tour::factory()->create(['name' => 'Cocora Trek']);
        $tourB = Tour::factory()->create(['name' => 'Andes Ride']);
        TourDate::factory()->for($tourA)->create(['starts_at' => now()->addDays(2)]);
        $latest = TourDate::factory()->for($tourB)->create(['starts_at' => now()->addDays(9)]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/tour-dates',
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $latest->id);
        $response->assertJsonPath('data.0.tour.name', 'Andes Ride');
        $response->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_forbidden_for_non_admin_member(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $guide = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($guide)->getJson(
            'http://demo.montree.test/api/v1/admin/tour-dates',
        );

        $response->assertStatus(403);
    }

    public function test_index_rejects_invalid_status_filter(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/tour-dates?status=invalido',
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_index_derives_and_filters_by_display_status(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $finished = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => TourDateStatus::Open,
        ]);
        $inProgress = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
            'status' => TourDateStatus::Open,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $finishedResponse = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/tour-dates?status='.TourDateDisplayStatus::Finished->value,
        );
        $inProgressResponse = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/tour-dates?status='.TourDateDisplayStatus::InProgress->value,
        );

        $finishedResponse->assertOk();
        $finishedResponse->assertJsonCount(1, 'data');
        $finishedResponse->assertJsonPath('data.0.id', $finished->id);
        $finishedResponse->assertJsonPath('data.0.display_status', TourDateDisplayStatus::Finished->value);

        $inProgressResponse->assertJsonCount(1, 'data');
        $inProgressResponse->assertJsonPath('data.0.id', $inProgress->id);
        $inProgressResponse->assertJsonPath('data.0.display_status', TourDateDisplayStatus::InProgress->value);
    }

    public function test_index_excludes_other_tenant_departures(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $tourA = Tour::factory()->create();
        TourDate::factory()->for($tourA)->create(['starts_at' => now()->addDays(3)]);
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        TourDate::factory()->for($tourB)->create(['starts_at' => now()->addDays(4)]);

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->getJson(
            'http://alpha.montree.test/api/v1/admin/tour-dates',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.tour.id', $tourA->id);
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
