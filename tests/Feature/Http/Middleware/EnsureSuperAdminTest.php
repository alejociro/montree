<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'super_admin.only'])
            ->get('_test/super-admin-only', fn () => ['ok' => true]);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_super_admin_passes_through(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        $response = $this->actingAs($user)->getJson(
            'http://admin.montree.test/_test/super-admin-only',
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }

    public function test_regular_user_gets_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            'http://admin.montree.test/_test/super-admin-only',
        );

        $response->assertForbidden();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $response = $this->getJson('http://admin.montree.test/_test/super-admin-only');

        $response->assertStatus(401);
    }
}
