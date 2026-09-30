<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\Booking;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

class PlatformChargeLedgerTest extends SuperAdminTestCase
{
    public function test_the_ledger_lists_the_charges_of_the_tenant_with_totals(): void
    {
        $tenant = $this->tenantWithCharges(['100.00', '50.00']);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}/charges"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SuperAdmin/Tenant/Charges')
                ->where('tenant.id', $tenant->id)
                ->where('totals.amount', '150.00')
                ->where('totals.count', 2)
                ->where('totals.currency', 'COP')
                ->has('charges.data', 2)
                ->has('charges.data.0', fn (AssertableInertia $row) => $row
                    ->hasAll(['id', 'charged_at', 'booking', 'base_amount', 'applied_rate', 'amount', 'currency', 'tier_from', 'tier_to', 'max_charge', 'was_capped', 'schedule_scope'])
                    ->etc()));
    }

    public function test_the_date_range_filters_the_ledger(): void
    {
        $tenant = $this->tenantWithCharges(['100.00', '50.00']);
        PlatformCharge::query()->latest('id')->first()?->update(['charged_at' => now()->subMonths(3)]);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}/charges?from=".now()->subMonth()->toDateString()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('charges.data', 1)
                ->where('totals.amount', '100.00'));
    }

    public function test_the_ledger_never_shows_charges_of_another_tenant(): void
    {
        $tenant = $this->tenantWithCharges(['100.00']);
        $other = $this->tenantWithCharges(['999.00']);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}/charges"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('charges.data', 1)
                ->where('totals.amount', '100.00'));

        $this->assertSame(1, PlatformCharge::query()->where('tenant_id', $other->id)->count());
    }

    public function test_an_invalid_range_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}/charges?from=2026-05-01&to=2026-01-01"))
            ->assertSessionHasErrors('to');
    }

    public function test_a_regular_user_cannot_read_the_ledger(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get($this->platformUrl("/super-admin/tenants/{$tenant->id}/charges"))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $amounts
     */
    private function tenantWithCharges(array $amounts): Tenant
    {
        $tenant = Tenant::factory()->create();
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);

        $tenant->makeCurrent();
        $bookings = Booking::factory()->count(count($amounts))->create();
        Tenant::forgetCurrent();

        foreach ($amounts as $index => $amount) {
            PlatformCharge::factory()->create([
                'tenant_id' => $tenant->id,
                'booking_id' => $bookings[$index]->id,
                'amount' => $amount,
                'currency' => 'COP',
            ]);
        }

        return $tenant;
    }
}
