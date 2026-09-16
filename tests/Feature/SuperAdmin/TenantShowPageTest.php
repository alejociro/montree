<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Booking;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

class TenantShowPageTest extends SuperAdminTestCase
{
    public function test_the_detail_ships_the_tenant_with_its_configuration_and_monthly_series(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $tenant->makeCurrent();
        $booking = Booking::factory()->create();
        Tenant::forgetCurrent();

        PlatformCharge::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'amount' => '9.00',
            'currency' => 'COP',
            'charged_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SuperAdmin/Tenant/Detail')
                ->where('tenant.slug', 'demo')
                ->has('tenant.configuration')
                ->where('charges_summary.total_amount', '9.00')
                ->where('charges_summary.total_count', 1)
                ->where('charges_summary.currency', 'COP')
                ->has('monthly.bookings', 12)
                ->has('monthly.charges', 12)
                ->has('roles'));
    }

    public function test_the_detail_counts_only_the_users_of_that_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->tenantAdminFor($tenant);
        $this->tenantAdminFor($other);
        $this->tenantAdminFor($other);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.stats.users_count', 1)->etc());
    }

    public function test_an_unknown_tenant_returns_404(): void
    {
        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/tenants/9999'))
            ->assertNotFound();
    }

    public function test_a_regular_user_cannot_open_the_detail(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}"))
            ->assertForbidden();
    }
}
