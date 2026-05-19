<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantRestoredNotification;
use App\Notifications\SuperAdmin\TenantSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_suspends_tenant_and_notifies_admin(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/status",
            ['status' => 'suspended', 'reason' => 'Pago vencido'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'suspended');

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Suspended, $fresh?->status);
        $this->assertNotNull($fresh?->suspended_at);

        Notification::assertSentTo($tenantAdmin, TenantSuspendedNotification::class);
    }

    public function test_restores_suspended_tenant_and_notifies(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->suspended()->create();
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/status",
            ['status' => 'active'],
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $this->assertNull($tenant->fresh()?->suspended_at);

        Notification::assertSentTo($tenantAdmin, TenantRestoredNotification::class);
    }

    public function test_suspend_without_reason_returns_422(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/status",
            ['status' => 'suspended'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }

    public function test_same_status_returns_409(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/status",
            ['status' => 'active'],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TENANT_STATUS_UNCHANGED');
    }

    public function test_non_super_admin_cannot_update_status(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}/status",
            ['status' => 'suspended', 'reason' => 'x'],
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
