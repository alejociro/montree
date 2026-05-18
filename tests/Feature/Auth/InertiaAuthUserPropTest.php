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
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_authenticated_user_inertia_props_include_tenant_role(): void
    {
        $user = User::factory()->create();
        $this->tenant->makeCurrent();
        app(AttachUserToTenant::class)->handle($user, $this->tenant, UserRole::Customer, 'manual');
        Tenant::forgetCurrent();

        $response = $this->actingAs($user)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.tenantRole', UserRole::Customer->value)
            ->where('auth.user.isSuperAdmin', false)
        );
    }

    public function test_super_admin_props_have_is_super_admin_true_and_null_tenant_role(): void
    {
        $superAdmin = User::factory()->create();
        setPermissionsTeamId(0);
        $superAdmin->syncRoles([UserRole::SuperAdmin->value]);

        $response = $this->actingAs($superAdmin)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isSuperAdmin', true)
            ->where('auth.user.tenantRole', null)
        );
    }

    public function test_user_without_tenant_relation_has_null_tenant_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('http://demo.montree.test/_test/auth-shared');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.tenantRole', null)
            ->where('auth.user.isSuperAdmin', false)
        );
    }
}
