<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateProviderTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_updates_a_provider(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $provider = Provider::factory()->create(['name' => 'Viejo Nombre']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/providers/{$provider->id}",
            ['name' => 'Transportes Andinos'],
        );

        $response->assertSessionHas('success');
        $this->assertSame('Transportes Andinos', $provider->fresh()?->name);
    }

    public function test_updating_a_provider_without_a_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $provider = Provider::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->put($this->host($tenant)."/admin/providers/{$provider->id}", ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_updating_a_provider_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $providerB = Provider::factory()->create(['name' => 'De Bravo']);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->put($this->host($tenantA)."/admin/providers/{$providerB->id}", ['name' => 'Hackeado'])
            ->assertNotFound();

        $this->assertSame('De Bravo', $providerB->fresh()?->name);
    }
}
