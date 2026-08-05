<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Onboarding\VerifyAgencyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
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
        return $this->postJson('http://montree.test/api/v1/onboarding/agencies', $this->payload($overrides));
    }

    public function test_creates_pending_tenant_with_founder_admin_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->register();

        $response->assertCreated()->assertExactJson([
            'data' => [
                'tenant' => [
                    'slug' => 'eco-adventures',
                    'domain' => 'eco-adventures.montree.test',
                    'status' => 'pending',
                ],
                'next' => 'verify_email',
                'email' => 'ana@eco.com',
            ],
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

    public function test_rejects_taken_subdomain(): void
    {
        Tenant::factory()->create(['slug' => 'eco-adventures']);

        $this->register()
            ->assertStatus(422)
            ->assertJsonValidationErrors('subdomain');
    }

    public function test_rejects_reserved_subdomain(): void
    {
        $this->register(['subdomain' => 'admin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subdomain');
    }

    public function test_rejects_invalid_password(): void
    {
        $this->register(['password' => '123', 'password_confirmation' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_rejects_already_registered_email_with_generic_message(): void
    {
        User::factory()->create(['email' => 'ana@eco.com']);

        $response = $this->register()
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(
            'No pudimos crear la cuenta con esos datos.',
            $response->json('errors.email.0'),
        );
    }

    public function test_does_not_persist_anything_when_validation_fails(): void
    {
        $this->register(['subdomain' => 'admin']);

        $this->assertDatabaseMissing('tenants', ['contact_email' => 'ana@eco.com']);
        $this->assertDatabaseMissing('users', ['email' => 'ana@eco.com']);
    }
}
