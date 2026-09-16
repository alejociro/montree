<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class StoreTenantTest extends SuperAdminTestCase
{
    public function test_super_admin_creates_a_tenant_with_its_initial_admin(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Eco Adventures',
                'slug' => 'eco-adventures',
                'plan' => 'professional',
                'admin_name' => 'Jane Owner',
                'admin_email' => 'jane@eco.test',
            ])
            ->assertSessionHas('success');

        $tenant = Tenant::query()->where('slug', 'eco-adventures')->firstOrFail();
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertSame('jane@eco.test', $tenant->contact_email);
        $this->assertDatabaseHas('tenant_configurations', ['tenant_id' => $tenant->id]);

        $admin = User::query()->where('email', 'jane@eco.test')->firstOrFail();
        $this->assertTrue($tenant->users()->where('users.id', $admin->id)->exists());

        setPermissionsTeamId($tenant->id);
        $admin->unsetRelation('roles');
        $this->assertTrue($admin->hasRole(UserRole::Admin->value));

        Notification::assertSentTo($admin, TenantUserInvitationNotification::class);
    }

    public function test_the_slug_and_the_admin_email_are_normalized(): void
    {
        Notification::fake();
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Eco Adventures',
                'slug' => 'ECO-Adventures',
                'plan' => 'professional',
                'admin_name' => 'Jane Owner',
                'admin_email' => 'Jane@ECO.test',
            ])
            ->assertSessionHas('success');

        $tenant = Tenant::query()->where('slug', 'eco-adventures')->firstOrFail();
        $this->assertSame('eco-adventures.montree.test', $tenant->domain);
        $this->assertSame('jane@eco.test', User::query()->whereKeyNot($superAdmin->id)->sole()->email);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        Tenant::factory()->create(['slug' => 'eco-adventures']);

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Otra',
                'slug' => 'eco-adventures',
                'plan' => 'basic',
                'admin_name' => 'Jane',
                'admin_email' => 'jane@otra.test',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_reserved_slug_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Admin',
                'slug' => 'admin',
                'plan' => 'basic',
                'admin_name' => 'Jane',
                'admin_email' => 'jane@admin.test',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_the_admin_fields_are_required(): void
    {
        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Eco',
                'slug' => 'eco',
                'plan' => 'basic',
            ])
            ->assertSessionHasErrors(['admin_name', 'admin_email']);
    }

    public function test_a_regular_user_cannot_create_a_tenant(): void
    {
        $this->actingAs(User::factory()->create())
            ->post($this->platformUrl('/super-admin/tenants'), [
                'name' => 'Eco',
                'slug' => 'eco',
                'plan' => 'basic',
                'admin_name' => 'Jane',
                'admin_email' => 'jane@eco.test',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenants', ['slug' => 'eco']);
    }
}
