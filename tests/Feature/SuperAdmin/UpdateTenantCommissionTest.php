<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\CommissionSchedule;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;

class UpdateTenantCommissionTest extends SuperAdminTestCase
{
    public function test_super_admin_sets_an_own_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => false,
                'tiers' => [
                    ['from' => '0', 'to' => null, 'rate' => '7.5'],
                ],
                'max_charge' => '50000',
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $schedule = CommissionSchedule::forTenantOnly($tenant->id);
        $this->assertNotNull($schedule);
        $this->assertSame('COP', $schedule->currency);
        $this->assertSame('7.50', $schedule->tiers[0]['rate']);
        $this->assertSame('50000.00', $schedule->max_charge);
    }

    public function test_switching_back_to_global_removes_the_own_schedule(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);
        CommissionSchedule::factory()->for($tenant)->create();

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => true,
            ]);

        $this->assertNull(CommissionSchedule::forTenantOnly($tenant->id));
    }

    public function test_a_rate_above_one_hundred_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => false,
                'tiers' => [
                    ['from' => '0', 'to' => null, 'rate' => '120'],
                ],
            ])
            ->assertSessionHasErrors('tiers');

        $this->assertNull(CommissionSchedule::forTenantOnly($tenant->id));
    }

    public function test_a_max_charge_of_zero_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => false,
                'tiers' => [
                    ['from' => '0', 'to' => null, 'rate' => '5'],
                ],
                'max_charge' => '0',
            ])
            ->assertSessionHasErrors('max_charge');

        $this->assertNull(CommissionSchedule::forTenantOnly($tenant->id));
    }

    public function test_tiers_with_a_gap_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => false,
                'tiers' => [
                    ['from' => '0', 'to' => '100', 'rate' => '5'],
                    ['from' => '150', 'to' => null, 'rate' => '3'],
                ],
            ])
            ->assertSessionHasErrors('tiers');
    }

    public function test_own_tiers_are_required_unless_using_global(): void
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), ['use_global' => false])
            ->assertSessionHasErrors('tiers');
    }

    public function test_a_regular_user_cannot_change_the_commission(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'use_global' => true,
            ])
            ->assertForbidden();
    }
}
