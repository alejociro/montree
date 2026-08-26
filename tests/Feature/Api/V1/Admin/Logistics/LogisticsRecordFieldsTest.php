<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Logistics;

use App\Enums\AccommodationType;
use App\Enums\CancellationPolicy;
use App\Enums\PaymentTerms;
use App\Enums\ProviderDocumentType;
use App\Enums\ProviderServiceType;
use App\Enums\RateUnit;
use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * La ficha completa del rediseño: los campos operativos de cada catálogo y sus
 * listas hijas —paradas, tarifas, documentos y habitaciones—.
 */
class LogisticsRecordFieldsTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_it_stores_a_full_route_with_its_stops(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/routes',
            [
                'name' => 'Ruta Cascadas',
                'kind' => RouteKind::Hiking->value,
                'difficulty' => TourDifficulty::Moderate->value,
                'start_point' => 'Plaza de Bolívar, Salento',
                'start_latitude' => 4.6376,
                'start_longitude' => -75.5706,
                'city' => 'Salento',
                'state' => 'Quindío',
                'distance_km' => 11.4,
                'duration_hours' => 6,
                'max_altitude_m' => 2860,
                'elevation_gain_m' => 640,
                'group_capacity' => 20,
                'seasons' => [RouteSeason::AllYear->value],
                'required_gear' => ['Calzado de trekking', 'Impermeable'],
                'emergency_contact' => 'Bomberos Salento',
                'stops' => [
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Pickup->value, 'time_label' => '8:00 a. m.'],
                    ['name' => 'Mirador', 'kind' => TourStopKind::Site->value, 'time_label' => null],
                    ['name' => 'Plaza de Bolívar', 'kind' => TourStopKind::Drop->value, 'time_label' => '5:00 p. m.'],
                ],
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.kind', 'hiking');
        $response->assertJsonPath('data.seasons.0', 'all_year');
        $response->assertJsonPath('data.required_gear.1', 'Impermeable');
        $response->assertJsonCount(3, 'data.stops');
        $response->assertJsonPath('data.stops.2.position', 3);
        $response->assertJsonPath('data.stops.2.kind', 'drop');

        $this->assertSame(3, Route::query()->firstOrFail()->stops()->count());
    }

    public function test_updating_a_route_rewrites_its_stops_in_order(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $route->stops()->createMany([
            ['position' => 1, 'name' => 'Vieja', 'kind' => TourStopKind::Pickup->value],
            ['position' => 2, 'name' => 'Otra vieja', 'kind' => TourStopKind::Site->value],
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/routes/{$route->id}",
            [
                'name' => $route->name,
                'stops' => [
                    ['name' => 'Nueva salida', 'kind' => TourStopKind::Pickup->value],
                ],
            ],
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data.stops');
        $response->assertJsonPath('data.stops.0.name', 'Nueva salida');
        $this->assertSame(1, $route->stops()->count());
    }

    /**
     * Parchear un campo suelto no puede borrar las listas: quien corrige el
     * teléfono desde otra pantalla no está pidiendo perder las paradas.
     */
    public function test_a_partial_update_keeps_the_stops_untouched(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $route = Route::factory()->create();
        $route->stops()->create([
            'position' => 1,
            'name' => 'Plaza',
            'kind' => TourStopKind::Pickup->value,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->putJson(
            "http://demo.montree.test/api/v1/admin/routes/{$route->id}",
            ['name' => 'Otro nombre'],
        )->assertOk();

        $this->assertSame(1, $route->stops()->count());
    }

    public function test_it_stores_a_provider_with_rates_and_documents(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/providers',
            [
                'name' => 'Transportes Andinos',
                'service_type' => ProviderServiceType::Transport->value,
                'legal_name' => 'Transportes Andinos S.A.S.',
                'tax_id' => '901.223.114-3',
                'payment_terms' => PaymentTerms::Advance50->value,
                'contact_name' => 'Jorge Rivas',
                'contact_phone' => '+57 310 555 8821',
                'city' => 'Armenia',
                'currency' => 'USD',
                'rates_valid_until' => '2026-12-31',
                'rates' => [
                    ['concept' => 'Bus 40 puestos', 'amount' => 320, 'unit' => RateUnit::PerService->value],
                    ['concept' => 'Van 12 puestos', 'amount' => 140, 'unit' => RateUnit::PerDay->value],
                ],
                'documents' => [
                    [
                        'kind' => ProviderDocumentType::LiabilityPolicy->value,
                        'number' => 'POL-90211',
                        'expires_at' => '2027-03-15',
                    ],
                ],
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.service_type', 'transport');
        $response->assertJsonPath('data.rates_valid_until', '2026-12-31');
        $response->assertJsonCount(2, 'data.rates');
        $response->assertJsonPath('data.rates.0.unit', 'per_service');
        $response->assertJsonPath('data.documents.0.expires_at', '2027-03-15');

        $provider = Provider::query()->firstOrFail();
        $this->assertSame(2, $provider->rates()->count());
        $this->assertSame(1, $provider->documents()->count());
    }

    public function test_it_stores_a_hotel_with_rooms_and_amenities(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/hotels',
            [
                'name' => 'Ecohotel La Montaña',
                'accommodation_type' => AccommodationType::Ecolodge->value,
                'star_rating' => 3,
                'tax_id' => '900.884.201-1',
                'address' => 'Vereda El Castillo, km 4',
                'city' => 'Salento',
                'total_capacity' => 28,
                'currency' => 'USD',
                'check_in' => '3:00 p. m.',
                'check_out' => '11:00 a. m.',
                'amenities' => ['breakfast', 'wifi', 'hot_water'],
                'cancellation_policy' => CancellationPolicy::Free48Hours->value,
                'payment_terms' => PaymentTerms::Advance30->value,
                'contact_name' => 'Recepción',
                'contact_phone' => '+57 312 400 1188',
                'rooms' => [
                    ['name' => 'Doble', 'quantity' => 6, 'nightly_rate' => 62],
                    ['name' => 'Familiar', 'quantity' => 2, 'nightly_rate' => 95],
                ],
            ],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.accommodation_type', 'ecolodge');
        $response->assertJsonPath('data.star_rating', 3);
        $response->assertJsonCount(3, 'data.amenities');
        $response->assertJsonCount(2, 'data.rooms');
        $response->assertJsonPath('data.rooms.0.name', 'Doble');

        $this->assertSame(2, Hotel::query()->firstOrFail()->rooms()->count());
    }

    public function test_an_unknown_enum_value_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/providers',
            ['name' => 'Raro', 'service_type' => 'teletransporte'],
        )->assertStatus(422)->assertJsonValidationErrors('service_type');
    }

    public function test_a_rate_without_a_concept_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->postJson(
            'http://demo.montree.test/api/v1/admin/providers',
            [
                'name' => 'Sin concepto',
                'rates' => [['amount' => 10, 'unit' => RateUnit::PerDay->value]],
            ],
        )->assertStatus(422)->assertJsonValidationErrors('rates.0.concept');
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

        $byCity = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/providers?search=Armenia',
        );
        $byCity->assertOk();
        $byCity->assertJsonCount(1, 'data');
        $byCity->assertJsonPath('data.0.id', $inArmenia->id);

        $byRate = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/providers?search=Chiva',
        );
        $byRate->assertOk();
        $byRate->assertJsonCount(1, 'data');
        $byRate->assertJsonPath('data.0.id', $withRate->id);
    }

    public function test_the_index_paginates_after_twelve_records(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Hotel::factory()->count(14)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $first = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/hotels',
        );
        $first->assertOk();
        $first->assertJsonCount(12, 'data');
        $first->assertJsonPath('meta.total', 14);
        $first->assertJsonPath('meta.last_page', 2);

        $second = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/hotels?page=2',
        );
        $second->assertOk();
        $second->assertJsonCount(2, 'data');
    }

    /**
     * El buscador de direcciones lo comparten el editor de ruta y las fichas:
     * quien solo administra logística también tiene que poder usarlo, sin
     * necesidad de poder editar tours.
     */
    public function test_logistics_manage_alone_can_use_the_geocoder(): void
    {
        Http::fake(['*' => Http::response([[
            'name' => 'Salento',
            'display_name' => 'Salento, Quindío, Colombia',
            'lat' => '4.63',
            'lon' => '-75.57',
        ]])]);

        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        setPermissionsTeamId($tenant->id);
        // `dashboard.view` es la llave del panel: sin ella el 403 vendría del
        // grupo de rutas y no probaría nada sobre el buscador.
        Permission::findOrCreate('dashboard.view', 'web');
        Permission::findOrCreate('logistics.manage', 'web');
        $user->givePermissionTo(['dashboard.view', 'logistics.manage']);

        $this->assertFalse($user->can('tours.update'));

        $this->actingAs($user)->getJson(
            'http://demo.montree.test/api/v1/admin/geocode?q=Salento',
        )->assertOk();
    }
}
