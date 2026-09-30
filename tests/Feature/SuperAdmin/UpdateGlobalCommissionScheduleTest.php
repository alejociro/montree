<?php

declare(strict_types=1);

namespace Tests\Feature\SuperAdmin;

use App\Models\CommissionSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

class UpdateGlobalCommissionScheduleTest extends SuperAdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // WHY: la vista nueva no pasó todavía por `npm run build` (lo corre
        // el orquestador una sola vez al final); el manifest de Vite no la
        // conoce hasta entonces.
        $this->withoutVite();
    }

    public function test_the_page_ships_the_current_global_schedule(): void
    {
        $this->actingAs($this->superAdmin())
            ->get($this->platformUrl('/super-admin/commission'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SuperAdmin/Commission/Index')
                ->has('schedule.currency')
                ->has('schedule.tiers'));
    }

    public function test_super_admin_updates_the_global_schedule(): void
    {
        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [
                    ['from' => '0', 'to' => '1000000', 'rate' => '10'],
                    ['from' => '1000000', 'to' => null, 'rate' => '6'],
                ],
                'max_charge' => '400000',
            ])
            ->assertRedirect($this->platformUrl('/super-admin/commission'));

        $schedule = CommissionSchedule::global();
        $this->assertCount(2, $schedule->tiers);
        $this->assertSame('10.00', $schedule->tiers[0]['rate']);
        $this->assertSame('400000.00', $schedule->max_charge);
    }

    public function test_a_null_max_charge_clears_the_cap(): void
    {
        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [['from' => '0', 'to' => null, 'rate' => '5']],
            ])
            ->assertRedirect($this->platformUrl('/super-admin/commission'));

        $this->assertNull(CommissionSchedule::global()->max_charge);
    }

    public function test_a_max_charge_of_zero_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [['from' => '0', 'to' => null, 'rate' => '5']],
                'max_charge' => '0',
            ])
            ->assertSessionHasErrors('max_charge');
    }

    public function test_a_schedule_with_a_gap_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [
                    ['from' => '0', 'to' => '100', 'rate' => '10'],
                    ['from' => '200', 'to' => null, 'rate' => '6'],
                ],
            ])
            ->assertSessionHasErrors('tiers');
    }

    public function test_boundaries_with_more_than_two_decimals_are_rejected(): void
    {
        // Sin esto, 499999.994 / 500000.006 pasan como contiguos y al redondear
        // dejan un hueco de un centavo en el que no se cobra comisión.
        $this->actingAs($this->superAdmin())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [
                    ['from' => '0', 'to' => '499999.994', 'rate' => '9'],
                    ['from' => '499999.994', 'to' => null, 'rate' => '7'],
                ],
            ])
            ->assertSessionHasErrors(['tiers.0.to', 'tiers.1.from']);
    }

    public function test_a_regular_user_cannot_update_the_global_schedule(): void
    {
        $this->actingAs(User::factory()->create())
            ->put($this->platformUrl('/super-admin/commission'), [
                'tiers' => [['from' => '0', 'to' => null, 'rate' => '5']],
            ])
            ->assertForbidden();
    }

    public function test_an_anonymous_visitor_is_sent_to_the_login(): void
    {
        $this->get($this->platformUrl('/super-admin/commission'))
            ->assertRedirect(route('login'));
    }
}
