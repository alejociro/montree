<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TourStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_activates_draft_tour_when_requirements_met(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        TourImage::factory()->for($tour)->cover()->create();
        TourDate::factory()->for($tour)->create([
            'status' => TourDateStatus::Open,
            'starts_at' => now()->addDays(7),
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $this->assertSame(TourStatus::Active, $tour->fresh()?->status);
    }

    public function test_activating_without_image_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        TourDate::factory()->for($tour)->create([
            'status' => TourDateStatus::Open,
            'starts_at' => now()->addDays(7),
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TOUR_NEEDS_IMAGE_TO_ACTIVATE');
    }

    public function test_activating_without_future_date_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        TourImage::factory()->for($tour)->cover()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TOUR_NEEDS_FUTURE_DATE_TO_ACTIVATE');
    }

    public function test_invalid_transition_returns_422(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Archived]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_active_to_paused_works(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'paused'],
        );

        $response->assertOk();
        $this->assertSame(TourStatus::Paused, $tour->fresh()?->status);
    }

    public function test_operator_cannot_archive(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $response = $this->actingAs($operator)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'archived'],
        );

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_status_change_other_tenant_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create(['status' => TourStatus::Draft]);

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->patchJson(
            "http://alpha.montree.test/api/v1/admin/tours/{$tourB->id}/status",
            ['status' => 'paused'],
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
