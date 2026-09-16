<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * El buscador de direcciones lo comparten el editor de ruta y las fichas: quien
 * solo administra logística también tiene que poder usarlo, sin necesidad de
 * poder editar tours. Sigue siendo un endpoint JSON.
 */
final class LogisticsGeocoderAccessTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

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

        $this->actingAs($user)
            ->getJson($this->host($tenant).'/api/v1/admin/geocode?q=Salento')
            ->assertOk();
    }
}
