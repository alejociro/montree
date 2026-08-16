<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_super_admin_creates_user_for_tenant(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Operator->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            "http://montree.test/api/v1/super-admin/tenants/{$tenant->id}/users",
            ['name' => 'New Guide', 'email' => 'guide@demo.test', 'role' => 'operator'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'guide@demo.test');

        $user = User::query()->where('email', 'guide@demo.test')->firstOrFail();
        $this->assertTrue(
            $tenant->users()
                ->where('users.id', $user->id)
                ->wherePivot('status', TenantMembershipStatus::Active->value)
                ->exists(),
        );

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(UserRole::Operator->value));

        Notification::assertSentTo($user, TenantUserInvitationNotification::class);
    }

    public function test_normalizes_the_email_before_persisting(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Operator->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            "http://montree.test/api/v1/super-admin/tenants/{$tenant->id}/users",
            ['name' => 'New Guide', 'email' => 'GUIDE@Demo.test', 'role' => 'operator'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.email', 'guide@demo.test');

        $this->assertSame('guide@demo.test', User::query()->whereKeyNot($superAdmin->id)->sole()->email);
    }

    public function test_existing_member_is_rejected(): void
    {
        Role::findOrCreate(UserRole::Guide->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $existing = User::factory()->create(['email' => 'member@demo.test']);
        $tenant->users()->attach($existing->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            "http://montree.test/api/v1/super-admin/tenants/{$tenant->id}/users",
            ['name' => 'Member', 'email' => 'member@demo.test', 'role' => 'guide'],
        );

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'TEAM_ALREADY_MEMBER');
    }

    public function test_invalid_role_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->postJson(
            "http://montree.test/api/v1/super-admin/tenants/{$tenant->id}/users",
            ['name' => 'X', 'email' => 'x@demo.test', 'role' => 'customer'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');
    }

    public function test_non_super_admin_cannot_create_user(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            "http://montree.test/api/v1/super-admin/tenants/{$tenant->id}/users",
            ['name' => 'X', 'email' => 'x@demo.test', 'role' => 'guide'],
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@demo.test']);
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
