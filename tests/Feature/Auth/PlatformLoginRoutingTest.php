<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformLoginRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

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

    private function member(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    private function loginOnPlatform(User $user): string
    {
        $response = $this->post('http://montree.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        return (string) $response->headers->get('Location');
    }

    public function test_admin_login_from_platform_hands_off_to_tenant_admin_dashboard(): void
    {
        $admin = $this->member(UserRole::Admin);

        $location = $this->loginOnPlatform($admin);
        $this->assertStringContainsString('demo.montree.test/auth/handoff/', $location);

        // The platform session must not linger.
        $this->assertGuest();

        // Following the handoff on the tenant host logs in and routes by role.
        $path = (string) parse_url($location, PHP_URL_PATH);
        $this->get('http://demo.montree.test'.$path)
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_inertia_login_returns_location_header_for_cross_host_handoff(): void
    {
        $admin = $this->member(UserRole::Admin);

        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->post('http://montree.test/login', [
                'email' => $admin->email,
                'password' => 'password',
            ]);

        // WHY: a cross-origin redirect would break the Inertia visit; the response
        // must be a 409 with X-Inertia-Location so the client does a full visit.
        $response->assertStatus(409);
        $this->assertStringContainsString(
            'demo.montree.test/auth/handoff/',
            (string) $response->headers->get('X-Inertia-Location'),
        );
    }

    public function test_customer_login_from_platform_hands_off_to_tenant_home(): void
    {
        $customer = $this->member(UserRole::Customer);

        $location = $this->loginOnPlatform($customer);
        $this->assertStringContainsString('demo.montree.test/auth/handoff/', $location);

        $path = (string) parse_url($location, PHP_URL_PATH);
        $this->get('http://demo.montree.test'.$path)
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_user_without_active_membership_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->post('http://montree.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
