<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_super_admin_creates_tenant_with_initial_admin(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            'http://montree.test/api/v1/super-admin/tenants',
            [
                'name' => 'Eco Adventures',
                'slug' => 'eco-adventures',
                'plan' => 'professional',
                'admin_name' => 'Jane Owner',
                'admin_email' => 'jane@eco.test',
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'eco-adventures');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.plan', 'professional');

        $tenant = Tenant::query()->where('slug', 'eco-adventures')->firstOrFail();
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('jane@eco.test', $tenant->contact_email);
        $this->assertDatabaseHas('tenant_configurations', ['tenant_id' => $tenant->id]);

        $admin = User::query()->where('email', 'jane@eco.test')->firstOrFail();
        $this->assertTrue(
            $tenant->users()
                ->where('users.id', $admin->id)
                ->wherePivot('status', TenantMembershipStatus::Active->value)
                ->exists(),
        );

        setPermissionsTeamId($tenant->id);
        $admin->unsetRelation('roles');
        $this->assertTrue($admin->hasRole(UserRole::Admin->value));

        Notification::assertSentTo($admin, TenantUserInvitationNotification::class);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Tenant::factory()->create(['slug' => 'taken']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            'http://montree.test/api/v1/super-admin/tenants',
            [
                'name' => 'Another',
                'slug' => 'taken',
                'plan' => 'basic',
                'admin_name' => 'Owner',
                'admin_email' => 'owner@another.test',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slug');
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            'http://montree.test/api/v1/super-admin/tenants',
            [
                'name' => 'Admin Panel',
                'slug' => 'admin',
                'plan' => 'basic',
                'admin_name' => 'Owner',
                'admin_email' => 'owner@admin.test',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slug');
    }

    public function test_missing_admin_fields_are_rejected(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            'http://montree.test/api/v1/super-admin/tenants',
            ['name' => 'Eco', 'slug' => 'eco', 'plan' => 'basic'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['admin_name', 'admin_email']);
    }

    public function test_non_super_admin_cannot_create_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            'http://montree.test/api/v1/super-admin/tenants',
            [
                'name' => 'Eco',
                'slug' => 'eco',
                'plan' => 'basic',
                'admin_name' => 'Owner',
                'admin_email' => 'owner@eco.test',
            ],
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('tenants', ['slug' => 'eco']);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }
}
