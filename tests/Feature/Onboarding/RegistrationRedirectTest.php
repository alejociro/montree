<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_register_on_the_platform_host_redirects_to_agency_onboarding(): void
    {
        $response = $this->get('http://montree.test/register');

        $response->assertRedirect(route('onboarding.start'));
    }

    public function test_registration_post_on_the_platform_host_never_reaches_fortify(): void
    {
        $response = $this->post('http://montree.test/register', [
            'name' => 'Ana Gómez',
            'email' => 'ana@eco.com',
            'password' => 'password-larga',
            'password_confirmation' => 'password-larga',
        ]);

        $response->assertRedirect(route('onboarding.start'));
        $this->assertSame(0, DB::table('users')->where('email', 'ana@eco.com')->count());
    }

    public function test_register_still_works_on_a_tenant_subdomain(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'eco-adventures']);
        TenantConfiguration::factory()->for($tenant)->create();

        $response = $this->get('http://eco-adventures.montree.test/register');

        $response->assertOk();
    }
}
