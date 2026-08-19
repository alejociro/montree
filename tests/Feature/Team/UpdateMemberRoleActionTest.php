<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Actions\Team\UpdateMemberRoleAction;
use App\Enums\UserRole;
use App\Exceptions\CrossTenantAccessException;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cierre del bug B3: el binding de `{user}` es global (la tabla `users` no está scopeada
 * por tenant), así que sin este chequeo el admin de una agencia podía reescribir los roles
 * de un usuario de otra — y de paso darle una membresía de facto en la suya.
 */
final class UpdateMemberRoleActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_updates_the_role_of_a_member_of_the_same_tenant(): void
    {
        $tenant = $this->makeTenant('demo');
        $member = $this->memberFor($tenant, UserRole::Guide);

        app(UpdateMemberRoleAction::class)->handle($tenant, $member, [UserRole::Sales->value]);

        setPermissionsTeamId($tenant->id);
        $member->unsetRelation('roles');
        $this->assertSame([UserRole::Sales->value], $member->getRoleNames()->all());
    }

    public function test_rejects_a_user_that_does_not_belong_to_the_tenant(): void
    {
        $tenantA = $this->makeTenant('demo');
        $tenantB = $this->makeTenant('otra');
        $foreigner = $this->memberFor($tenantB, UserRole::Guide);

        $this->expectException(CrossTenantAccessException::class);

        try {
            app(UpdateMemberRoleAction::class)->handle($tenantA, $foreigner, [UserRole::Admin->value]);
        } catch (CrossTenantAccessException $exception) {
            $this->assertSame('CROSS_TENANT_ACCESS', $exception->errorCode);
            $this->assertSame(403, $exception->getStatusCode());

            throw $exception;
        }
    }

    public function test_does_not_leak_roles_into_the_other_tenant_when_rejected(): void
    {
        $tenantA = $this->makeTenant('demo');
        $tenantB = $this->makeTenant('otra');
        $foreigner = $this->memberFor($tenantB, UserRole::Guide);

        try {
            app(UpdateMemberRoleAction::class)->handle($tenantA, $foreigner, [UserRole::Admin->value]);
        } catch (CrossTenantAccessException) {
            // La aserción es el estado, no la excepción: ver el test anterior.
        }

        setPermissionsTeamId($tenantA->id);
        $foreigner->unsetRelation('roles');
        $this->assertSame([], $foreigner->getRoleNames()->all());

        setPermissionsTeamId($tenantB->id);
        $foreigner->unsetRelation('roles');
        $this->assertSame([UserRole::Guide->value], $foreigner->getRoleNames()->all());
    }

    public function test_endpoint_answers_403_with_the_cross_tenant_error_code(): void
    {
        $tenantA = $this->makeTenant('demo');
        $tenantB = $this->makeTenant('otra');
        $admin = $this->memberFor($tenantA, UserRole::Admin);
        $foreigner = $this->memberFor($tenantB, UserRole::Guide);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/users/{$foreigner->id}/role",
            ['roles' => [UserRole::Admin->value]],
        );

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'CROSS_TENANT_ACCESS');
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::factory()->create([
            'slug' => $slug,
            'domain' => "{$slug}.montree.test",
        ]);
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
