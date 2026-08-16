<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
