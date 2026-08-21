<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

class GeocodeControllerTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_it_returns_normalised_places_for_an_address(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'name' => 'Plaza de Bolívar',
                    'display_name' => 'Plaza de Bolívar, Salento, Quindío, Colombia',
                    'lat' => '4.6376',
                    'lon' => '-75.5706',
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson($this->url('Plaza de Bolívar Salento'));

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Plaza de Bolívar');
        $response->assertJsonPath('data.0.latitude', 4.6376);
        $response->assertJsonPath('data.0.longitude', -75.5706);
    }

    public function test_it_falls_back_to_the_first_segment_when_the_place_has_no_name(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'display_name' => 'Carrera 7 #12-34, Bogotá, Colombia',
                    'lat' => '4.6',
                    'lon' => '-74.08',
                ],
            ]),
        ]);

        $response = $this->actingAs($this->admin())->getJson($this->url('Carrera 7 12 34'));

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Carrera 7 #12-34');
    }

    /**
     * El buscador no puede tumbar el formulario: si el servicio no responde, la
     * agencia sigue marcando el punto arrastrando el pin en el mapa.
     */
    public function test_it_returns_an_empty_list_when_the_service_fails(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $response = $this->actingAs($this->admin())->getJson($this->url('Salento'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_it_caches_the_answer_so_the_same_address_hits_the_network_once(): void
    {
        Cache::flush();
        Http::fake([
            '*' => Http::response([[
                'name' => 'Salento',
                'display_name' => 'Salento, Quindío, Colombia',
                'lat' => '4.63',
                'lon' => '-75.57',
            ]]),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->getJson($this->url('Salento Quindío'))->assertOk();
        $this->actingAs($admin)->getJson($this->url('Salento Quindío'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_a_short_term_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->getJson($this->url('ab'))
            ->assertUnprocessable();
    }

    public function test_a_role_without_tour_edition_cannot_search(): void
    {
        $tenant = $this->currentTenant();

        $this->actingAs($this->memberFor($tenant, UserRole::Guide))
            ->getJson($this->url('Salento'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        return $this->memberFor($this->currentTenant(), UserRole::Admin);
    }

    private function currentTenant(): Tenant
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            $tenant = $this->makeTenant();
            $tenant->makeCurrent();
        }

        return $tenant;
    }

    private function url(string $term): string
    {
        return 'http://demo.montree.test/api/v1/admin/geocode?q='.urlencode($term);
    }
}
