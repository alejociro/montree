<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\CommissionType;
use App\Models\Tenant;
use App\Models\User;

class UpdateTenantCommissionTest extends SuperAdminTestCase
{
    public function test_super_admin_sets_a_percentage_commission(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'type' => 'percentage',
                'value' => '7.5',
            ])
            ->assertRedirect($this->platformUrl("/super-admin/tenants/{$tenant->id}"));

        $tenant->refresh();
        $this->assertSame(CommissionType::Percentage, $tenant->commission_type);
        $this->assertSame('7.50', $tenant->commission_value);
    }

    public function test_a_null_type_disables_the_commission(): void
    {
        $tenant = Tenant::factory()->create([
            'commission_type' => CommissionType::Fixed,
            'commission_value' => '5.00',
        ]);

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), ['type' => null]);

        $tenant->refresh();
        $this->assertNull($tenant->commission_type);
        $this->assertNull($tenant->commission_value);
    }

    public function test_a_percentage_above_one_hundred_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'type' => 'percentage',
                'value' => '120',
            ])
            ->assertSessionHasErrors('value');

        $this->assertNull($tenant->fresh()?->commission_type);
    }

    public function test_a_type_without_value_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), ['type' => 'fixed'])
            ->assertSessionHasErrors('value');
    }

    public function test_a_regular_user_cannot_change_the_commission(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put($this->platformUrl("/super-admin/tenants/{$tenant->id}/commission"), [
                'type' => 'fixed',
                'value' => '1',
            ])
            ->assertForbidden();
    }
}
