<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantRestoredNotification;
use App\Notifications\SuperAdmin\TenantSuspendedNotification;
use Illuminate\Support\Facades\Notification;

class UpdateTenantStatusTest extends SuperAdminTestCase
{
    public function test_suspending_a_tenant_notifies_its_admins(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create();
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $this->actingAs($superAdmin)
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/status"), [
                'status' => 'suspended',
                'reason' => 'Falta de pago',
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Suspended, $fresh?->status);
        $this->assertNotNull($fresh?->suspended_at);

        Notification::assertSentTo($tenantAdmin, TenantSuspendedNotification::class);
    }

    public function test_restoring_a_suspended_tenant_notifies_its_admins(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->suspended()->create();
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $this->actingAs($superAdmin)
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/status"), ['status' => 'active']);

        $this->assertNull($tenant->fresh()?->suspended_at);

        Notification::assertSentTo($tenantAdmin, TenantRestoredNotification::class);
    }

    public function test_suspending_without_a_reason_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/status"), ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');
    }

    public function test_setting_the_same_status_comes_back_as_a_field_error(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/status"), ['status' => 'active'])
            ->assertSessionHasErrors('status');
    }

    public function test_a_regular_user_cannot_change_the_status(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/status"), ['status' => 'suspended', 'reason' => 'x'])
            ->assertForbidden();
    }
}
