<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_guest_sees_the_landing_on_the_platform_host(): void
    {
        $response = $this->get('http://montree.test/');

        $response->assertOk();
        $response->assertSee('Landing');
    }

    public function test_super_admin_is_redirected_to_their_panel_from_the_platform_home(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        $response = $this->actingAs($user)->get('http://montree.test/');

        $response->assertRedirect('/super-admin/dashboard');
    }
}
