<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteProviderTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_deleting_an_unused_provider_succeeds(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $provider = Provider::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/providers/{$provider->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('providers', ['id' => $provider->id]);
    }

    public function test_deleting_a_provider_used_by_a_departure_is_blocked(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $provider = Provider::factory()->create();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->create(['provider_id' => $provider->id]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/providers/{$provider->id}");

        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseHas('providers', ['id' => $provider->id]);
    }

    public function test_deleting_a_provider_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $providerB = Provider::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->delete($this->host($tenantA)."/admin/providers/{$providerB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('providers', ['id' => $providerB->id]);
    }
}
