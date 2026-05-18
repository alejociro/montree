<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Tenant;
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

    public function test_super_admin_can_list_tenants(): void
    {
        Tenant::factory()->create(['name' => 'Eco Travels']);
        Tenant::factory()->create(['name' => 'Urban Tours']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->getJson(
            'http://admin.montree.test/api/v1/super-admin/tenants',
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'status', 'plan', 'users_count', 'revenue_30d']],
            'meta', 'links',
        ]);
    }

    public function test_super_admin_can_filter_tenants_by_search(): void
    {
        Tenant::factory()->create(['name' => 'Eco Adventures', 'slug' => 'eco-adventures']);
        Tenant::factory()->create(['name' => 'Urban Tours', 'slug' => 'urban-tours']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->getJson(
            'http://admin.montree.test/api/v1/super-admin/tenants?search=eco',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', 'eco-adventures');
    }

    public function test_super_admin_can_view_tenant_detail(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo']);
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->getJson(
            "http://admin.montree.test/api/v1/super-admin/tenants/{$tenant->id}",
        );

        $response->assertOk();
        $response->assertJsonPath('data.id', $tenant->id);
        $response->assertJsonPath('data.slug', 'demo');
    }

    public function test_non_super_admin_cannot_list_tenants(): void
    {
        Tenant::factory()->count(2)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            'http://admin.montree.test/api/v1/super-admin/tenants',
        );

        $response->assertForbidden();
    }

    public function test_caps_per_page_at_100(): void
    {
        Tenant::factory()->count(3)->create();
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)->getJson(
            'http://admin.montree.test/api/v1/super-admin/tenants?per_page=500',
        );

        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }
}
