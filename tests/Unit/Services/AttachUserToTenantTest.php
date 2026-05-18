<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttachUserToTenantTest extends TestCase
{
    use RefreshDatabase;

    private AttachUserToTenant $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->service = app(AttachUserToTenant::class);
        $this->tenant = Tenant::factory()->create();
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_creates_active_pivot_when_membership_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->service->handle($user, $this->tenant, UserRole::Customer, 'registration');

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => TenantMembershipStatus::Active->value,
        ]);
    }

    public function test_assigns_role_scoped_to_tenant(): void
    {
        $user = User::factory()->create();

        $this->service->handle($user, $this->tenant, UserRole::Admin, 'invite');

        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($user->fresh()->hasRole(UserRole::Admin->value));
    }

    public function test_is_idempotent_when_called_twice(): void
    {
        $user = User::factory()->create();

        $this->service->handle($user, $this->tenant, UserRole::Customer, 'registration');
        $this->service->handle($user, $this->tenant, UserRole::Customer, 'login');

        $pivotCount = $this->tenant->users()->where('users.id', $user->id)->count();
        $this->assertSame(1, $pivotCount);
    }

    public function test_does_not_leak_role_to_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $this->service->handle($user, $this->tenant, UserRole::Admin, 'invite');

        setPermissionsTeamId($otherTenant->id);
        $user->unsetRelation('roles');
        $this->assertFalse($user->fresh()->hasRole(UserRole::Admin->value));
    }
}
