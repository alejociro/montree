<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Onboarding\VerifyAgencyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ResendVerificationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_a_missing_email_in_spanish(): void
    {
        $this->post('http://montree.test/onboarding/resend-verification', [])
            ->assertSessionHasErrors(['email' => 'Ingresa tu correo.']);
    }

    public function test_reports_a_malformed_email_in_spanish(): void
    {
        $this->post('http://montree.test/onboarding/resend-verification', ['email' => 'no-es-un-correo'])
            ->assertSessionHasErrors(['email' => 'Correo inválido.']);
    }

    public function test_finds_the_pending_agency_from_an_uppercased_email(): void
    {
        Notification::fake();

        Tenant::factory()->create([
            'contact_email' => 'ana@eco.com',
            'status' => TenantStatus::Pending,
        ]);
        $founder = User::factory()->create(['email' => 'ana@eco.com']);

        $this->post('http://montree.test/onboarding/resend-verification', ['email' => 'ANA@Eco.com'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($founder, VerifyAgencyEmail::class);
    }
}
