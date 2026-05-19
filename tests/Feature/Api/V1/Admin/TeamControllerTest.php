<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_index_lists_staff_members_and_excludes_customers(): void
    {
        $tenant = $this->makeTenant();

        $admin = $this->memberFor($tenant, UserRole::Admin, ['name' => 'Tenant Admin']);
        $this->memberFor($tenant, UserRole::Operator, ['name' => 'Tenant Operator']);
        $this->memberFor($tenant, UserRole::Guide, ['name' => 'Tenant Guide']);
        $this->memberFor($tenant, UserRole::Customer, ['name' => 'Tenant Customer']);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/users',
        );

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $roles = collect($response->json('data'))->pluck('role')->all();
        sort($roles);
        $this->assertSame(['admin', 'guide', 'operator'], $roles);

        $emails = collect($response->json('data'))->pluck('email')->all();
        $this->assertNotContains('customer@demo.montree.test', $emails);
    }

    public function test_index_requires_authentication(): void
    {
        $this->makeTenant();

        $response = $this->getJson('http://demo.montree.test/api/v1/admin/users');

        $response->assertStatus(401);
    }

    public function test_index_isolates_team_members_per_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $adminA = $this->memberFor($tenantA, UserRole::Admin, ['name' => 'Alpha Admin']);
        $this->memberFor($tenantB, UserRole::Admin, ['name' => 'Bravo Admin']);

        Tenant::forgetCurrent();

        $response = $this->actingAs($adminA)->getJson(
            'http://alpha.montree.test/api/v1/admin/users',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alpha Admin');
    }

    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
