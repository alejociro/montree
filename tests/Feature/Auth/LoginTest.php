<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
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
            'name' => 'Demo Eco',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $user = User::factory()->create();
        $this->tenant->makeCurrent();
        app(AttachUserToTenant::class)->handle($user, $this->tenant, UserRole::Customer, 'manual');
        Tenant::forgetCurrent();

        $response = $this->post('http://demo.montree.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    }

    public function test_login_creates_tenant_user_when_missing_with_customer_role(): void
    {
        $user = User::factory()->create();

        $this->post('http://demo.montree.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => TenantMembershipStatus::Active->value,
        ]);
        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($user->fresh()->hasRole(UserRole::Customer->value));
    }

    public function test_login_rejects_suspended_membership_with_logout_and_error(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Suspended->value,
            'suspended_at' => now(),
        ]);

        $response = $this->post('http://demo.montree.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('http://demo.montree.test/login');
        $response->assertSessionHasErrors('email');
        $errors = session('errors')->getBag('default')->get('email');
        $this->assertSame('Tu cuenta está suspendida en esta agencia.', $errors[0]);
    }

    public function test_login_invalid_credentials_keeps_user_guest(): void
    {
        $user = User::factory()->create();

        $this->post('http://demo.montree.test/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_rate_limit_blocks_after_five_attempts(): void
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post('http://demo.montree.test/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
