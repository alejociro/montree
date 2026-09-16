<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Auth\CrossHostLoginHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El login cross-host destruye la sesión del host de origen, así que «Recordarme»
 * solo sobrevive si viaja dentro del token de handoff y el host destino emite ahí
 * la cookie recaller (spec criterio E).
 */
final class RememberMeSurvivesHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_destination_host_issues_the_recaller_when_remember_is_checked(): void
    {
        $admin = $this->superAdmin();
        $this->tenant();

        $response = $this->followHandoff($admin, remember: true);

        $response->assertRedirect('/super-admin/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($this->recallerFrom($response));
        $this->assertNotNull($admin->fresh()?->getRememberToken());
    }

    public function test_no_recaller_is_issued_when_remember_is_not_checked(): void
    {
        $admin = $this->superAdmin();
        $this->tenant();

        $response = $this->followHandoff($admin, remember: false);

        $this->assertAuthenticatedAs($admin);
        $this->assertNull($this->recallerFrom($response));
    }

    public function test_the_handoff_payload_defaults_to_not_remembering(): void
    {
        $admin = $this->superAdmin();
        $handoff = app(CrossHostLoginHandoff::class);

        $payload = $handoff->consume($handoff->issue($admin, '/super-admin/dashboard'));

        $this->assertNotNull($payload);
        $this->assertFalse($payload->remember);
    }

    public function test_a_token_issued_for_one_user_never_logs_in_another(): void
    {
        $admin = $this->superAdmin();
        $other = User::factory()->create();
        $handoff = app(CrossHostLoginHandoff::class);

        $payload = $handoff->consume($handoff->issue($admin, '/super-admin/dashboard', true));

        $this->assertNotNull($payload);
        $this->assertSame($admin->id, $payload->userId);
        $this->assertNotSame($other->id, $payload->userId);
    }

    private function followHandoff(User $admin, bool $remember): TestResponse
    {
        $login = $this->post('http://demo.montree.test/login', [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => $remember,
        ]);

        $path = (string) parse_url((string) $login->headers->get('Location'), PHP_URL_PATH);

        return $this->get('http://montree.test'.$path);
    }

    private function recallerFrom(TestResponse $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web') && $cookie->getValue() !== '') {
                return $cookie->getValue();
            }
        }

        return null;
    }

    private function tenant(): Tenant
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }
}
