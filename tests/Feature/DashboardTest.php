<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipStatus;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('http://demo.montree.test/dashboard');
        $response->assertRedirect(route('login'));
    }

    public function test_active_members_are_redirected_to_account_bookings()
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('http://demo.montree.test/dashboard');

        $response->assertRedirect('/account/bookings');
    }

    public function test_non_members_cannot_reach_the_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('http://demo.montree.test/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
