<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Notifications\Auth\TenantAwareVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TenantAwareEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());

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

    public function test_unverified_user_can_access_login_page_without_verification(): void
    {
        $response = $this->get('http://demo.montree.test/login');

        $response->assertOk();
    }

    public function test_verify_email_route_marks_user_verified_with_branding_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->tenant->makeCurrent();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, TenantAwareVerifyEmail::class, function (TenantAwareVerifyEmail $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertStringContainsString('Demo Eco', $mail->subject);

            return true;
        });

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_verification_email_endpoint_works(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->post('http://demo.montree.test/email/verification-notification');

        $response->assertSessionHas('status', 'verification-link-sent');
        Notification::assertSentTo($user, TenantAwareVerifyEmail::class);
    }
}
