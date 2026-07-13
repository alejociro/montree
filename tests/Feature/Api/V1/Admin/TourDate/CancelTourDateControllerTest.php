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

class CancelTourDateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_cancels_open_date(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Open]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}/cancel",
            ['reason' => 'Clima adverso'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', TourDateStatus::Cancelled->value);
        $this->assertSame(TourDateStatus::Cancelled, $tourDate->fresh()?->status);
    }

    public function test_cancelling_already_cancelled_date_returns_409(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['status' => TourDateStatus::Cancelled]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tour-dates/{$tourDate->id}/cancel",
            [],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TOUR_DATE_ALREADY_CANCELLED');
    }

    public function test_tenant_isolation_cancel_other_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $dateB = TourDate::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->patchJson(
            "http://alpha.montree.test/api/v1/admin/tour-dates/{$dateB->id}/cancel",
            [],
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
