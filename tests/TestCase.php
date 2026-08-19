<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * WHY: roles y permisos son datos de referencia, no fixtures de un test. Desde F018 la
     * autorización se resuelve contra `permissions`, así que sin este catálogo cualquier
     * `can()` responde false y todo el panel devolvería 403 en la suite.
     *
     * @var class-string<Seeder>
     */
    protected $seeder = RolesAndPermissionsSeeder::class;

    /**
     * WHY: el cliente HTTP de pruebas manda `Accept-Language: en-us,en;q=0.5` por defecto,
     * asi que desde multilanguage-es-en la suite entera correria en ingles y cada
     * assertion sobre un mensaje en espanol fallaria. Se fija el idioma del proyecto y
     * cada test que necesite otro lo sobrescribe con `withHeader`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'es');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
