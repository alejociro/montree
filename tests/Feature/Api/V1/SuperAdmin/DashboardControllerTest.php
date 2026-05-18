<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_super_admin_can_view_dashboard_metrics(): void
    {
        Tenant::factory()->count(2)->create();
        Tenant::factory()->basic()->create();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->getJson(
            'http://admin.montree.test/api/v1/super-admin/dashboard',
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'totals' => ['tenants', 'active_tenants', 'users', 'bookings_this_month', 'revenue_this_month', 'platform_commission_this_month'],
                'growth' => ['tenants_new_this_month', 'bookings_growth_pct'],
                'plan_distribution' => ['basic', 'professional', 'enterprise'],
            ],
        ]);
        $this->assertSame(3, $response->json('data.totals.tenants'));
    }

    public function test_regular_authenticated_user_receives_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            'http://admin.montree.test/api/v1/super-admin/dashboard',
        );

        $response->assertForbidden();
    }

    public function test_tenant_admin_receives_403_on_super_admin_endpoint(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->tenantAdminFor($tenant);

        $response = $this->actingAs($admin)->getJson(
            'http://admin.montree.test/api/v1/super-admin/dashboard',
        );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_receives_401_or_redirect(): void
    {
        $response = $this->getJson('http://admin.montree.test/api/v1/super-admin/dashboard');

        $response->assertStatus(401);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function tenantAdminFor(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }
}
