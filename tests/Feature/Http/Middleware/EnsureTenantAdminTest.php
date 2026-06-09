<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureTenantAdminTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'tenant_admin.only'])
            ->get('_test/admin-only', fn () => ['ok' => true]);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    private function memberWithRole(UserRole $role, TenantMembershipStatus $status): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => $status->value,
            'joined_at' => now(),
        ]);
        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    public function test_active_admin_passes_through(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin, TenantMembershipStatus::Active);

        $this->actingAs($admin)
            ->getJson('http://demo.montree.test/_test/admin-only')
            ->assertOk();
    }

    public function test_suspended_admin_is_forbidden_mid_session(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin, TenantMembershipStatus::Suspended);

        $this->actingAs($admin)
            ->getJson('http://demo.montree.test/_test/admin-only')
            ->assertForbidden();
    }

    public function test_active_customer_without_admin_role_is_forbidden(): void
    {
        $customer = $this->memberWithRole(UserRole::Customer, TenantMembershipStatus::Active);

        $this->actingAs($customer)
            ->getJson('http://demo.montree.test/_test/admin-only')
            ->assertForbidden();
    }
}
