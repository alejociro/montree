<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\RateUnit;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * Los dos catálogos viajan juntos en `Admin/Logistics/Index`: el buscador los
 * filtra a la vez y cada bandeja pagina por su cuenta.
 */
final class LogisticsIndexTest extends TestCase
{
    use DepartureScenario;
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

    /**
     * El buscador de la barra promete «nombre, municipio, contacto o tarifa».
     */
    public function test_the_search_reaches_the_city_and_the_rate_concept(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $inArmenia = Provider::factory()->create(['name' => 'Alfa', 'city' => 'Armenia']);
        $withRate = Provider::factory()->create(['name' => 'Beta', 'city' => 'Pereira']);
        $withRate->rates()->create([
            'position' => 1,
            'concept' => 'Chiva rumbera',
            'amount' => 200,
            'unit' => RateUnit::PerService->value,
        ]);
        Provider::factory()->create(['name' => 'Gamma', 'city' => 'Manizales']);

        $byCity = $this->actingAs($admin)->get($this->host($tenant).'/admin/logistics?search=Armenia');
        $byCity->assertOk();
        $byCity->assertInertia(fn (AssertableInertia $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.id', $inArmenia->id)
        );

        $byRate = $this->actingAs($admin)->get($this->host($tenant).'/admin/logistics?search=Chiva');
        $byRate->assertOk();
        $byRate->assertInertia(fn (AssertableInertia $page) => $page
            ->has('providers.data', 1)
            ->where('providers.data.0.id', $withRate->id)
        );
    }

    public function test_each_tray_paginates_after_twelve_records(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Hotel::factory()->count(14)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $first = $this->actingAs($admin)->get($this->host($tenant).'/admin/logistics');
        $first->assertOk();
        $first->assertInertia(fn (AssertableInertia $page) => $page
            ->has('hotels.data', 12)
            ->where('hotels.meta.total', 14)
            ->where('hotels.meta.last_page', 2)
        );

        $second = $this->actingAs($admin)->get($this->host($tenant).'/admin/logistics?hotels_page=2');
        $second->assertOk();
        $second->assertInertia(fn (AssertableInertia $page) => $page->has('hotels.data', 2));
    }

    public function test_index_rejects_an_unknown_tab(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->from($this->host($tenant).'/admin/logistics')
            ->get($this->host($tenant).'/admin/logistics?tab=barcos')
            ->assertSessionHasErrors('tab');
    }

    public function test_index_is_forbidden_for_a_guide(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $guide = $this->guideFor($tenant);

        $this->actingAs($guide)->get($this->host($tenant).'/admin/logistics')->assertForbidden();
    }

    public function test_index_hides_the_catalogues_of_another_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        Hotel::factory()->create();
        Provider::factory()->create();

        $tenantA->makeCurrent();
        $response = $this->actingAs($adminA)->get($this->host($tenantA).'/admin/logistics');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('hotels.data', 0)
            ->has('providers.data', 0)
        );
    }
}
