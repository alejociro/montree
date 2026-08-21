<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Models\Tenant;
use App\Models\TourDate;
use Database\Seeders\DemoTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9 — el dato demo tampoco puede nacer ilegal. Si el seeder repartiera guías al
 * azar, la primera pantalla que abre cualquiera del equipo mostraría un solape
 * que la aplicación jura que no puede existir.
 */
final class DemoSeederAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_demo_seeder_never_leaves_a_guide_with_two_crossed_departures(): void
    {
        $this->seed(DemoTenantSeeder::class);
        Tenant::forgetCurrent();

        $departures = TourDate::query()
            ->withoutGlobalScopes()
            ->occupying()
            ->get()
            ->groupBy('guide_id');

        $this->assertGreaterThan(0, $departures->count());

        foreach ($departures as $guideId => $ofGuide) {
            $days = [];

            foreach ($ofGuide as $departure) {
                foreach ($departure->occupiedDays() as $day) {
                    $key = $day->toDateString();
                    $this->assertArrayNotHasKey($key, $days, "El guía {$guideId} quedó con dos salidas el {$key}.");
                    $days[$key] = true;
                }
            }
        }
    }
}
