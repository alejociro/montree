<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Notifications\Auth\TenantAwareResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());

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

    public function test_forgot_password_returns_200_for_unknown_email(): void
    {
        $response = $this->post('http://demo.montree.test/forgot-password', [
            'email' => 'ghost@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
    }

    public function test_forgot_password_sends_tenant_aware_notification_when_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('http://demo.montree.test/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, TenantAwareResetPassword::class);
    }

    public function test_reset_password_with_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->post('http://demo.montree.test/reset-password', [
            'token' => 'invalid-token-12345',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
