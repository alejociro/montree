<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Invitar al equipo, reenviar la invitación y aceptarla (F018 fase 3A).
 */
final class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_inviting_a_new_person_leaves_them_pending_and_emails_the_invitation(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson('http://demo.montree.test/api/v1/admin/users', [
            'email' => 'nueva@demo.test',
            'name' => 'Nueva Persona',
            'role' => UserRole::Sales->value,
        ]);

        $response->assertCreated();

        $invitee = User::query()->where('email', 'nueva@demo.test')->firstOrFail();
        $this->assertSame(
            TenantMembershipStatus::Invited,
            $invitee->membershipFor($tenant)?->status,
        );
        Notification::assertSentTo($invitee, TenantUserInvitationNotification::class);
    }

    public function test_resends_the_invitation_to_a_pending_member(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $invitee = $this->memberFor($tenant, UserRole::Guide, TenantMembershipStatus::Invited);

        $response = $this->actingAs($admin)->postJson($this->url($invitee));

        $response->assertOk();
        $response->assertJsonPath('data.status', 'invited');
        Notification::assertSentTo($invitee, TenantUserInvitationNotification::class);
    }

    public function test_refuses_to_resend_to_someone_who_already_accepted(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $member = $this->memberFor($tenant, UserRole::Guide);

        $response = $this->actingAs($admin)->postJson($this->url($member));

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TEAM_INVITATION_ALREADY_ACCEPTED');
        Notification::assertNothingSent();
    }

    public function test_refuses_to_resend_to_a_member_of_another_agency(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $otherTenant = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $foreigner = $this->memberFor($otherTenant, UserRole::Guide, TenantMembershipStatus::Invited);

        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->postJson($this->url($foreigner));

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'CROSS_TENANT_ACCESS');
        Notification::assertNothingSent();
    }

    public function test_setting_the_password_accepts_the_invitation(): void
    {
        $tenant = $this->makeTenant();
        $invitee = $this->memberFor($tenant, UserRole::Guide, TenantMembershipStatus::Invited);

        event(new PasswordReset($invitee));

        $membership = $invitee->membershipFor($tenant);
        $this->assertSame(TenantMembershipStatus::Active, $membership?->status);
        $this->assertNotNull($membership?->joined_at);
    }

    private function url(User $member): string
    {
        return "http://demo.montree.test/api/v1/admin/users/{$member->id}/resend-invitation";
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(
        Tenant $tenant,
        UserRole $role,
        TenantMembershipStatus $status = TenantMembershipStatus::Active,
    ): User {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => $status->value,
            'invited_at' => now(),
            'joined_at' => $status === TenantMembershipStatus::Invited ? null : now(),
        ]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
