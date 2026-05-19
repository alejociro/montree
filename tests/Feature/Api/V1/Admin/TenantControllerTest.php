<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_can_update_tenant_basic_info(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant',
            [
                'name' => 'New Demo Name',
                'contact_email' => 'updated@demo.montree.test',
                'contact_phone' => '+57 311 111 1111',
            ],
        );

        $response->assertOk();
        $response->assertJsonPath('data.tenant.name', 'New Demo Name');
        $response->assertJsonPath('data.tenant.contact_email', 'updated@demo.montree.test');
        $this->assertSame('New Demo Name', $tenant->fresh()?->name);
    }

    public function test_non_admin_cannot_update_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $member = $this->memberFor($tenant, UserRole::Operator);

        $response = $this->actingAs($member)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant',
            [
                'name' => 'Should Fail',
                'contact_email' => 'fail@demo.montree.test',
            ],
        );

        $response->assertStatus(403);
    }

    public function test_validates_required_fields(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->adminFor($tenant);

        $response = $this->actingAs($admin)->putJson(
            'http://demo.montree.test/api/v1/admin/tenant',
            [
                'name' => '',
                'contact_email' => 'not-an-email',
            ],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'contact_email']);
    }

    private function adminFor(Tenant $tenant): User
    {
        return $this->memberFor($tenant, UserRole::Admin);
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
