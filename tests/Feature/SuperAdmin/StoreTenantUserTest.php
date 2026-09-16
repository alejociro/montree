<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class StoreTenantUserTest extends SuperAdminTestCase
{
    public function test_super_admin_adds_a_user_to_a_tenant(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Operator->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'New Guide',
                'email' => 'guide@demo.test',
                'role' => 'operator',
            ])
            ->assertSessionHas('success');

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

    public function test_the_email_is_normalized(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Operator->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'New Guide',
                'email' => 'GUIDE@Demo.test',
                'role' => 'operator',
            ]);

        $this->assertSame('guide@demo.test', User::query()->whereKeyNot($superAdmin->id)->sole()->email);
    }

    public function test_sales_is_an_assignable_role(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Sales->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'New Sales',
                'email' => 'sales@demo.test',
                'role' => 'sales',
            ])
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'sales@demo.test')->firstOrFail();

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $this->assertTrue($user->hasRole(UserRole::Sales->value));
    }

    public function test_an_existing_member_comes_back_as_a_field_error(): void
    {
        Role::findOrCreate(UserRole::Guide->value, 'web');

        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $existing = User::factory()->create(['email' => 'member@demo.test']);
        $tenant->users()->attach($existing->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'Member',
                'email' => 'member@demo.test',
                'role' => 'guide',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'X',
                'email' => 'x@demo.test',
                'role' => 'customer',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_a_regular_user_cannot_add_members(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);

        $this->actingAs(User::factory()->create())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/users"), [
                'name' => 'X',
                'email' => 'x@demo.test',
                'role' => 'guide',
            ])
            ->assertForbidden();
    }
}
