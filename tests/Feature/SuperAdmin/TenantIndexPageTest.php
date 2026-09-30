<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CommissionSchedule;
use App\Models\Payment;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

class TenantIndexPageTest extends SuperAdminTestCase
{
    public function test_the_listing_ships_every_row_with_its_stats_and_commission(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Eco Travels']);
        TenantConfiguration::factory()->for($tenant)->create(['currency' => 'COP']);
        CommissionSchedule::factory()->for($tenant)->withTiers([
            ['from' => '0.00', 'to' => null, 'rate' => '5.00'],
        ])->create(['currency' => 'COP']);

        $tenant->makeCurrent();
        $booking = Booking::factory()->create();
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => PaymentStatus::Completed,
            'processed_at' => now(),
            'amount' => '80.00',
        ]);
        Tenant::forgetCurrent();

        PlatformCharge::factory()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'amount' => '4.00',
            'charged_at' => now(),
        ]);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/tenants'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SuperAdmin/Tenant/Index')
                ->has('tenants.data', 1)
                ->where('tenants.data.0.name', 'Eco Travels')
                ->where('tenants.data.0.can_enter', true)
                ->where('tenants.data.0.commission.scope', 'tenant')
                ->where('tenants.data.0.commission.tiers_count', 1)
                ->where('tenants.data.0.stats.bookings_count_30d', 1)
                ->where('tenants.data.0.stats.revenue_30d', '80.00')
                ->where('tenants.data.0.stats.charges_30d', '4.00')
                ->etc());
    }

    public function test_the_search_filters_by_name_or_slug(): void
    {
        Tenant::factory()->create(['name' => 'Eco Adventures', 'slug' => 'eco-adventures']);
        Tenant::factory()->create(['name' => 'Urban Tours', 'slug' => 'urban-tours']);

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/tenants?search=eco'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tenants.data', 1)
                ->where('tenants.data.0.slug', 'eco-adventures')
                ->where('filters.search', 'eco')
                ->etc());
    }

    public function test_a_suspended_tenant_cannot_be_entered(): void
    {
        Tenant::factory()->suspended()->create();

        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/tenants?status=suspended'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tenants.data', 1)
                ->where('tenants.data.0.can_enter', false)
                ->etc());
    }

    public function test_an_unknown_sort_column_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/tenants?sort=contact_email'))
            ->assertSessionHasErrors('sort');
    }

    public function test_a_regular_user_cannot_list_tenants(): void
    {
        Tenant::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->get($this->platformUrl('/super-admin/tenants'))
            ->assertForbidden();
    }
}
