<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureTenantMemberTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'tenant_member.only'])
            ->get('_test/member-only', fn () => ['ok' => true]);

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

    private function attach(User $user, TenantMembershipStatus $status): void
    {
        $this->tenant->users()->attach($user->id, [
            'status' => $status->value,
            'joined_at' => now(),
        ]);
    }

    public function test_active_member_passes_through(): void
    {
        $user = User::factory()->create();
        $this->attach($user, TenantMembershipStatus::Active);

        $this->actingAs($user)
            ->getJson('http://demo.montree.test/_test/member-only')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_non_member_is_forbidden_on_json_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('http://demo.montree.test/_test/member-only')
            ->assertForbidden();
    }

    public function test_suspended_member_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->attach($user, TenantMembershipStatus::Suspended);

        $this->actingAs($user)
            ->getJson('http://demo.montree.test/_test/member-only')
            ->assertForbidden();
    }

    public function test_non_member_web_request_is_logged_out_and_redirected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('http://demo.montree.test/_test/member-only');

        $response->assertRedirect();
        $this->assertGuest();
    }

    public function test_super_admin_is_redirected_away_from_customer_routes(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        $this->actingAs($user)
            ->get('http://demo.montree.test/_test/member-only')
            ->assertRedirect('http://demo.montree.test');
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('http://demo.montree.test/_test/member-only')
            ->assertStatus(401);
    }
}
