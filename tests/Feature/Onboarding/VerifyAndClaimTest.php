<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerifyAndClaimTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->pending()->create([
            'slug' => 'eco',
            'domain' => 'eco.montree.test',
            'contact_email' => 'ana@eco.com',
            'trial_ends_at' => null,
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();

        $this->founder = User::factory()->unverified()->create(['email' => 'ana@eco.com']);
        $this->tenant->users()->attach($this->founder->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        Role::findOrCreate('admin', 'web');
        setPermissionsTeamId($this->tenant->id);
        $this->founder->assignRole('admin');
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    private function verifyUrl(?int $tenantId = null, ?int $userId = null): string
    {
        URL::forceRootUrl('http://montree.test');

        try {
            return URL::temporarySignedRoute('onboarding.verify', now()->addMinutes(60), [
                'tenant' => $tenantId ?? $this->tenant->id,
                'user' => $userId ?? $this->founder->id,
            ]);
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_verification_activates_tenant_and_starts_trial(): void
    {
        $response = $this->get($this->verifyUrl());

        $response->assertRedirect();

        $this->tenant->refresh();
        $this->founder->refresh();

        $this->assertSame(TenantStatus::Active, $this->tenant->status);
        $this->assertNotNull($this->tenant->trial_ends_at);
        $this->assertEqualsWithDelta(14, now()->diffInDays($this->tenant->trial_ends_at, false), 1);
        $this->assertNotNull($this->founder->email_verified_at);
    }

    public function test_verification_redirects_to_signed_claim_on_subdomain(): void
    {
        $location = (string) $this->get($this->verifyUrl())->headers->get('Location');

        $this->assertStringStartsWith('http://eco.montree.test/onboarding/claim', $location);
        $this->assertStringContainsString('signature=', $location);
        $this->assertStringContainsString('nonce=', $location);
    }

    public function test_claim_logs_in_founder_and_redirects_to_admin_dashboard(): void
    {
        $claimUrl = (string) $this->get($this->verifyUrl())->headers->get('Location');

        $response = $this->get($claimUrl);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->founder);
    }

    public function test_claim_nonce_is_single_use(): void
    {
        $claimUrl = (string) $this->get($this->verifyUrl())->headers->get('Location');

        $this->get($claimUrl)->assertRedirect('/admin/dashboard');

        $this->get($claimUrl)->assertRedirect(route('login'));
    }

    public function test_expired_or_invalid_signature_shows_expired_page(): void
    {
        $this->withoutVite();

        $response = $this->get($this->verifyUrl().'tampered');

        $response->assertStatus(403);
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('Onboarding/VerificationExpired', false));
    }
}
