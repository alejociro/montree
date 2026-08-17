<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Rbac\PermissionCatalogSeederTest;
use Tests\TestCase;

class InertiaAuthUserPropTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Route::middleware('web')->get('_test/auth-shared', fn () => Inertia::render('Welcome'));

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

    public function test_authenticated_user_inertia_props_include_the_permissions_of_their_roles(): void
    {
        $user = User::factory()->create();
        $this->tenant->makeCurrent();
        app(AttachUserToTenant::class)->handle($user, $this->tenant, UserRole::Operator, 'manual');
        Tenant::forgetCurrent();

        $response = $this->actingAs($user)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.isSuperAdmin', false)
            ->has('auth.user.permissions', 13)
            ->where('auth.permissions', fn (Collection $permissions): bool => $permissions->contains('tours.create')
                && ! $permissions->contains('team.view')
            )
        );
    }

    public function test_super_admin_props_carry_the_whole_permission_catalog(): void
    {
        $superAdmin = User::factory()->create();
        setPermissionsTeamId(0);
        $superAdmin->syncRoles([UserRole::SuperAdmin->value]);

        $response = $this->actingAs($superAdmin)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isSuperAdmin', true)
            ->has('auth.user.permissions', PermissionCatalogSeederTest::CATALOG_SIZE)
        );
    }

    public function test_user_without_tenant_relation_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.permissions', [])
            ->where('auth.user.isSuperAdmin', false)
        );
    }
}
