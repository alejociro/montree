<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;

class EnterTenantTest extends SuperAdminTestCase
{
    public function test_super_admin_lands_on_the_tenant_panel_with_their_own_session(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        TenantConfiguration::factory()->for($tenant)->create();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/enter"));

        $response->assertRedirect();
        $handoff = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://demo.montree.test/auth/handoff/', $handoff);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($handoff)->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($superAdmin);

        $this->get('http://demo.montree.test/admin/dashboard')->assertOk();
    }

    public function test_the_handoff_token_cannot_be_replayed(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $handoff = (string) $this->actingAs($superAdmin)
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/enter"))
            ->headers->get('Location');

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($handoff)->assertRedirect('/admin/dashboard');

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get($handoff)->assertRedirect(route('login'));
    }

    public function test_entering_a_suspended_tenant_is_rejected(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/enter"))
            ->assertStatus(409);
    }

    public function test_a_regular_user_cannot_enter_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/enter"))
            ->assertForbidden();
    }

    public function test_a_user_without_membership_still_gets_403_on_the_tenant_panel(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);

        $this->actingAs(User::factory()->create())
            ->get('http://demo.montree.test/admin/dashboard')
            ->assertForbidden();
    }
}
