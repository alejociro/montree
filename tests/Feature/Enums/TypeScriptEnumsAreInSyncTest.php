<?php

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Console\Commands\SyncTypeScriptEnumsCommand;
use Tests\TestCase;

/**
 * El espejo de TypeScript se genera desde `app/Enums`. Este test es el único
 * motivo por el que se puede confiar en él: si alguien agrega, quita o renombra
 * un caso y no corre `php artisan enums:typescript`, la suite lo dice aquí y no
 * un usuario tres pantallas más allá.
 */
final class TypeScriptEnumsAreInSyncTest extends TestCase
{
    public function test_the_generated_file_matches_the_php_enums(): void
    {
        $this->artisan('enums:typescript --check')
            ->assertExitCode(0);
    }

    public function test_the_generated_file_carries_the_do_not_edit_notice(): void
    {
        $this->assertStringContainsString(
            'ARCHIVO GENERADO',
            (string) file_get_contents(base_path(SyncTypeScriptEnumsCommand::OUTPUT)),
        );
    }
}
