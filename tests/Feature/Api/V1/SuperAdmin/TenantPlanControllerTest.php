<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantPlan;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantPlanChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_super_admin_can_change_plan_and_notifies_admin(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->basic()->create();
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/plan",
            ['plan' => 'enterprise'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.plan', 'enterprise');
        $this->assertSame(TenantPlan::Enterprise, $tenant->fresh()?->plan);

        Notification::assertSentTo($tenantAdmin, TenantPlanChangedNotification::class);
    }

    public function test_invalid_plan_returns_422(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/plan",
            ['plan' => 'imaginary'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan');
    }

    public function test_downgrade_exceeding_limits_is_allowed_and_logged(): void
    {
        Log::spy();
        Notification::fake();

        $tenant = Tenant::factory()->enterprise()->create();
        $superAdmin = $this->superAdmin();

        $tenant->makeCurrent();
        Tour::factory()->count(11)->create();
        Tenant::forgetCurrent();

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/plan",
            ['plan' => 'basic'],
        );

        $response->assertOk();
        $this->assertSame(TenantPlan::Basic, $tenant->fresh()?->plan);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_non_super_admin_cannot_change_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/plan",
            ['plan' => 'professional'],
        );

        $response->assertForbidden();
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
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }
}
