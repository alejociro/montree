<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La moneda de la agencia se configura desde los dos paneles y solo admite la
 * lista soportada (spec §H).
 */
final class TenantCurrencyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_tenant_admin_changes_the_currency(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('demo', ['currency' => 'USD']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post("http://{$tenant->domain}/admin/tenant/configuration", ['currency' => 'COP'])
            ->assertSessionHasNoErrors();

        $this->assertSame('COP', $configuration->fresh()?->currency);
    }

    public function test_the_tenant_admin_cannot_set_an_unsupported_currency(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('demo', ['currency' => 'USD']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post("http://{$tenant->domain}/admin/tenant/configuration", ['currency' => 'XXX'])
            ->assertSessionHasErrors('currency');

        $this->assertSame('USD', $configuration->fresh()?->currency);
    }

    public function test_the_super_admin_changes_the_currency_of_a_tenant(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('demo', ['currency' => 'USD']);
        Tenant::forgetCurrent();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), ['currency' => 'PEN'])
            ->assertSessionHasNoErrors();

        $this->assertSame('PEN', $configuration->fresh()?->currency);
    }

    public function test_the_super_admin_cannot_set_an_unsupported_currency(): void
    {
        [$tenant, $configuration] = $this->tenantWithConfiguration('demo', ['currency' => 'USD']);
        Tenant::forgetCurrent();

        $this->actingAs($this->superAdmin())
            ->post($this->platformUrl("/super-admin/tenants/{$tenant->id}/configuration"), ['currency' => 'XXX'])
            ->assertSessionHasErrors('currency');

        $this->assertSame('USD', $configuration->fresh()?->currency);
    }

    /**
     * La moneda de una agencia nunca se le escribe a otra.
     */
    public function test_changing_the_currency_leaves_the_other_tenants_alone(): void
    {
        [$tenant] = $this->tenantWithConfiguration('demo', ['currency' => 'USD']);
        [, $otherConfiguration] = $this->tenantWithConfiguration('bravo', ['currency' => 'EUR']);
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post("http://{$tenant->domain}/admin/tenant/configuration", ['currency' => 'COP'])
            ->assertSessionHasNoErrors();

        $this->assertSame('EUR', $otherConfiguration->fresh()?->currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Tenant, 1: TenantConfiguration}
     */
    private function tenantWithConfiguration(string $slug, array $attributes = []): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug, 'domain' => "{$slug}.montree.test"]);
        $configuration = TenantConfiguration::factory()->for($tenant)->create($attributes);
        $tenant->makeCurrent();

        return [$tenant, $configuration];
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        Role::findOrCreate($role->value, 'web');

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function platformUrl(string $path): string
    {
        return 'http://'.config('montree.platform_host').$path;
    }
}
