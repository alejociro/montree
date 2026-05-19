<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Notifications\Auth\TenantAwareVerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());

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

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('http://demo.montree.test/register');

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('http://demo.montree.test/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => TenantMembershipStatus::Active->value,
        ]);
        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($user->fresh()->hasRole(UserRole::Customer->value));
    }

    public function test_register_with_existing_email_returns_422_generic_message(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('http://demo.montree.test/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $errors = session('errors')->getBag('default')->get('email');
        $this->assertSame('Las credenciales no son válidas.', $errors[0]);
        $this->assertGuest();
    }

    public function test_register_with_invalid_data_returns_validation_errors(): void
    {
        $response = $this->post('http://demo.montree.test/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'a',
            'password_confirmation' => 'b',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    public function test_register_sends_tenant_aware_verification_email(): void
    {
        Notification::fake();

        $this->post('http://demo.montree.test/register', [
            'name' => 'Verify Me',
            'email' => 'verify@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'verify@example.com')->firstOrFail();
        Notification::assertSentTo($user, TenantAwareVerifyEmail::class);
    }

    public function test_register_dispatches_registered_event_and_logs_user_in(): void
    {
        Event::fake([Registered::class]);

        $this->post('http://demo.montree.test/register', [
            'name' => 'Logged In',
            'email' => 'login@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Event::assertDispatched(Registered::class);
        $this->assertAuthenticated();
    }
}
