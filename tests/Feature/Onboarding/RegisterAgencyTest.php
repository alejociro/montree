<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Actions\Onboarding\RegisterAgencyAction;
use App\Enums\TenantStatus;
use App\Exceptions\SubdomainTakenException;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Onboarding\VerifyAgencyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RegisterAgencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agency_name' => 'Eco Adventures',
            'subdomain' => 'eco-adventures',
            'founder_name' => 'Ana Gómez',
            'email' => 'ana@eco.com',
            'password' => 'super-secret-123',
            'password_confirmation' => 'super-secret-123',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->post('http://montree.test/onboarding/agencies', $this->payload($overrides));
    }

    public function test_creates_pending_tenant_with_founder_admin_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->register();

        $response->assertRedirect(route('onboarding.check-email'));
        $response->assertSessionHas('onboarding.pending', [
            'email' => 'ana@eco.com',
            'agency_name' => 'Eco Adventures',
        ]);

        $tenant = Tenant::query()->where('slug', 'eco-adventures')->firstOrFail();
        $this->assertSame(TenantStatus::Pending, $tenant->status);
        $this->assertDatabaseHas('tenant_configurations', ['tenant_id' => $tenant->id]);

        $founder = User::query()->where('email', 'ana@eco.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $founder->id,
            'status' => 'active',
        ]);

        setPermissionsTeamId($tenant->id);
        $founder->unsetRelation('roles');
        $this->assertTrue($founder->hasRole('admin'));

        Notification::assertSentTo($founder, VerifyAgencyEmail::class);
    }

    public function test_normalizes_subdomain_and_email_before_persisting(): void
    {
        Notification::fake();

        $this->register([
            'subdomain' => 'Eco-Adventures',
            'email' => 'ANA@Eco.com',
        ])->assertRedirect(route('onboarding.check-email'));

        $tenant = Tenant::query()->sole();
        $this->assertSame('eco-adventures', $tenant->slug);
        $this->assertSame('eco-adventures.montree.test', $tenant->domain);
        $this->assertSame('ana@eco.com', $tenant->contact_email);

        $this->assertSame('ana@eco.com', User::query()->sole()->email);
    }

    public function test_rejects_taken_subdomain(): void
    {
        Tenant::factory()->create(['slug' => 'eco-adventures']);

        $this->register()->assertSessionHasErrors('subdomain');
    }

    public function test_rejects_reserved_subdomain(): void
    {
        $this->register(['subdomain' => 'admin'])->assertSessionHasErrors('subdomain');
    }

    public function test_rejects_invalid_password(): void
    {
        $this->register(['password' => '123', 'password_confirmation' => '123'])
            ->assertSessionHasErrors('password');
    }

    public function test_reports_password_mismatch_on_the_confirmation_field(): void
    {
        $this->register(['password_confirmation' => 'something-else-456'])
            ->assertSessionHasErrors(['password_confirmation' => 'Las contraseñas no coinciden.'])
            ->assertSessionDoesntHaveErrors('password');
    }

    public function test_reports_every_validation_failure_in_spanish(): void
    {
        $response = $this->post('http://montree.test/onboarding/agencies', [
            'agency_name' => '',
            'subdomain' => 'Eco Adventures!',
            'founder_name' => '',
            'email' => 'no-es-un-correo',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors([
            'agency_name' => 'Ingresa el nombre de tu agencia.',
            'subdomain' => 'Solo minúsculas, números y guiones, sin empezar por guion.',
            'founder_name' => 'Ingresa tu nombre.',
            'email' => 'Correo inválido.',
            'password' => 'Ingresa una contraseña.',
            'password_confirmation' => 'Confirma tu contraseña.',
        ]);
    }

    public function test_reports_password_strength_failures_in_spanish(): void
    {
        Password::defaults(fn (): Password => Password::min(12)->mixedCase()->letters()->numbers()->symbols());

        $this->register(['password' => 'abcdefghijkl', 'password_confirmation' => 'abcdefghijkl'])
            ->assertSessionHasErrors([
                'password' => 'Combina mayúsculas y minúsculas.',
            ]);
    }

    public function test_rejects_already_registered_email_with_generic_message(): void
    {
        User::factory()->create(['email' => 'ana@eco.com']);

        $this->register()->assertSessionHasErrors([
            'email' => 'No pudimos crear la cuenta con esos datos.',
        ]);
    }

    public function test_does_not_persist_anything_when_validation_fails(): void
    {
        $this->register(['subdomain' => 'admin']);

        $this->assertDatabaseMissing('tenants', ['contact_email' => 'ana@eco.com']);
        $this->assertDatabaseMissing('users', ['email' => 'ana@eco.com']);
    }

    public function test_never_flashes_the_password_to_the_session(): void
    {
        $this->register(['subdomain' => 'admin']);

        $flashed = session('_old_input', []);

        $this->assertArrayNotHasKey('password', $flashed);
        $this->assertArrayNotHasKey('password_confirmation', $flashed);
    }

    /**
     * WHY: a slug that survives validation can still collide against the unique
     * index when two founders register it at the same instant. The exception
     * handler turns that race into a field error instead of a 409 that Inertia
     * would surface as an error modal — and must not flash the password doing it.
     */
    public function test_slug_collision_race_returns_a_subdomain_field_error(): void
    {
        Route::middleware('web')->post('__test/slug-collision', function (): never {
            throw new SubdomainTakenException;
        });

        $response = $this->from('http://montree.test/start')
            ->post('http://montree.test/__test/slug-collision', $this->payload());

        $response->assertRedirect('http://montree.test/start');
        $response->assertSessionHasErrors(['subdomain' => 'Ese subdominio ya fue reclamado.']);
        $response->assertSessionMissing('_old_input');
    }

    public function test_translates_a_unique_slug_violation_into_a_subdomain_error(): void
    {
        Tenant::factory()->create(['slug' => 'eco-adventures']);

        $this->expectException(SubdomainTakenException::class);

        app(RegisterAgencyAction::class)->handle($this->payload());
    }

    public function test_does_not_report_a_duplicate_email_as_a_taken_subdomain(): void
    {
        User::factory()->create(['email' => 'ana@eco.com']);

        $this->expectException(QueryException::class);

        app(RegisterAgencyAction::class)->handle($this->payload(['subdomain' => 'eco']));
    }

    public function test_check_email_page_reads_the_pending_registration_from_the_session(): void
    {
        Notification::fake();

        $this->register();

        $this->get('http://montree.test/onboarding/check-email')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/CheckEmail')
                ->where('email', 'ana@eco.com')
                ->where('agencyName', 'Eco Adventures'));
    }

    public function test_check_email_page_exposes_nothing_without_a_pending_registration(): void
    {
        $this->get('http://montree.test/onboarding/check-email?email=victima@ejemplo.com&agency=Falsa')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/CheckEmail')
                ->where('email', null)
                ->where('agencyName', null));
    }
}
