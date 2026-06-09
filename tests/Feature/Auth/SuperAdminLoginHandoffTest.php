<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Auth\CrossHostLoginHandoff;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminLoginHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function test_login_on_platform_goes_straight_to_super_admin_dashboard(): void
    {
        $admin = $this->superAdmin();

        $response = $this->post('http://montree.test/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/super-admin/dashboard', $location);
        $this->assertStringNotContainsString('/auth/handoff/', $location);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_from_a_tenant_subdomain_hands_off_to_platform(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->superAdmin();

        $response = $this->post('http://demo.montree.test/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('montree.test/auth/handoff/', $location);
        $this->assertStringNotContainsString('demo.montree.test/auth/handoff/', $location);

        // The tenant-host session must not linger.
        $this->assertGuest();

        // Following the handoff on the platform logs the super admin in there.
        $path = (string) parse_url($location, PHP_URL_PATH);
        $this->get('http://montree.test'.$path)
            ->assertRedirect('/super-admin/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_handoff_token_is_single_use(): void
    {
        $admin = $this->superAdmin();
        $handoff = app(CrossHostLoginHandoff::class);

        $token = $handoff->issue($admin, '/super-admin/dashboard');

        $this->assertNotNull($handoff->consume($token));
        $this->assertNull($handoff->consume($token));
    }

    public function test_invalid_handoff_token_redirects_to_login(): void
    {
        $response = $this->get('http://montree.test/auth/handoff/does-not-exist');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
