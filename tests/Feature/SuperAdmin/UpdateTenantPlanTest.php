<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantPlanChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class UpdateTenantPlanTest extends SuperAdminTestCase
{
    public function test_changing_the_plan_notifies_the_tenant_admins(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->basic()->create();
        $superAdmin = $this->superAdmin();
        $tenantAdmin = $this->tenantAdminFor($tenant);

        $this->actingAs($superAdmin)
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/plan"), ['plan' => 'enterprise'])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $this->assertSame(TenantPlan::Enterprise, $tenant->fresh()?->plan);

        Notification::assertSentTo($tenantAdmin, TenantPlanChangedNotification::class);
    }

    public function test_an_unknown_plan_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/plan"), ['plan' => 'imaginary'])
            ->assertSessionHasErrors('plan');
    }

    public function test_a_downgrade_over_the_limits_is_allowed_and_logged(): void
    {
        Log::spy();
        Notification::fake();

        $tenant = Tenant::factory()->enterprise()->create();
        $superAdmin = $this->superAdmin();

        $tenant->makeCurrent();
        Tour::factory()->count(11)->create();
        Tenant::forgetCurrent();

        $this->actingAs($superAdmin)
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/plan"), ['plan' => 'basic']);

        $this->assertSame(TenantPlan::Basic, $tenant->fresh()?->plan);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_regular_user_cannot_change_the_plan(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch($this->platformUrl("/super-admin/tenants/{$tenant->id}/plan"), ['plan' => 'professional'])
            ->assertForbidden();
    }
}
