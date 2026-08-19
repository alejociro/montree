<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LastLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_real_login_records_the_last_access(): void
    {
        $tenant = $this->makeTenant();
        $member = $this->memberFor($tenant, UserRole::Admin);

        $this->assertNull($member->last_login_at);

        $this->post('http://demo.montree.test/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($member);
        $this->assertNotNull($member->fresh()?->last_login_at);
    }

    /**
     * WHY: multi-tenancy.md §10.1.1 — entrar desde el host de plataforma dispara dos
     * eventos Login (el del host origen y el de `auth.handoff`). Es un solo acceso.
     */
    public function test_the_cross_host_handoff_does_not_count_as_a_second_access(): void
    {
        $tenant = $this->makeTenant();
        $member = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->post('http://montree.test/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);

        $firstAccess = $member->fresh()?->last_login_at;
        $this->assertNotNull($firstAccess);

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('demo.montree.test/auth/handoff/', $location);

        // WHY: 30s y no más — el token de handoff vive 60s (CrossHostLoginHandoff::TTL).
        $this->travel(30)->seconds();
        $this->get('http://demo.montree.test'.(string) parse_url($location, PHP_URL_PATH));

        $this->assertAuthenticatedAs($member);
        $this->assertTrue($firstAccess->equalTo($member->fresh()?->last_login_at));
    }

    public function test_a_later_login_moves_the_last_access_forward(): void
    {
        $tenant = $this->makeTenant();
        $member = $this->memberFor($tenant, UserRole::Admin);

        $this->post('http://demo.montree.test/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);
        $firstAccess = $member->fresh()?->last_login_at;

        $this->post('http://demo.montree.test/logout');
        $this->travel(2)->hours();

        $this->post('http://demo.montree.test/login', [
            'email' => $member->email,
            'password' => 'password',
        ]);

        $this->assertTrue($firstAccess->lessThan($member->fresh()?->last_login_at));
    }

    private function makeTenant(): Tenant
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
