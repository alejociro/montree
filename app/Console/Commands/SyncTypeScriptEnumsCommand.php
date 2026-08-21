<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionEnum;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Vierte los enums de PHP a TypeScript.
 *
 * WHY: hasta hoy cada enum tenía un espejo escrito a mano en `resources/js`
 * ("mismos valores y mismo orden", decía el comentario). Un espejo a mano no
 * avisa cuando deja de serlo: `guide-availability.ts` declaraba
 * `status: 'open' | 'closed'` y sobrevivió a que `full` entrara al scope de
 * ocupación. El backend es la única fuente; el archivo generado no se edita y
 * `--check` hace fallar la suite si alguien cambia un enum y no lo regenera.
 */
final class SyncTypeScriptEnumsCommand extends Command
{
    protected $signature = 'enums:typescript {--check : Fail if the generated file is stale instead of writing it}';

    protected $description = 'Generate the TypeScript mirror of the PHP string-backed enums';

    public const OUTPUT = 'resources/js/types/enums.generated.ts';

    private const SOURCE = 'app/Enums';

    public function handle(): int
    {
        $target = base_path(self::OUTPUT);
        $expected = $this->render();

        if ($this->option('check')) {
            if (is_file($target) && file_get_contents($target) === $expected) {
                $this->components->info(self::OUTPUT.' is up to date.');

                return self::SUCCESS;
            }

            $this->components->error(self::OUTPUT.' is stale. Run `php artisan enums:typescript`.');

            return self::FAILURE;
        }

        file_put_contents($target, $expected);
        $this->components->info('Wrote '.self::OUTPUT.'.');

        return self::SUCCESS;
    }

    /**
     * El contenido íntegro del archivo generado, en el formato que ya deja
     * Prettier (4 espacios, comillas simples, punto y coma).
     */
    public function render(): string
    {
        $blocks = array_map(
            fn (ReflectionEnum $enum): string => $this->block($enum),
            $this->enums(),
        );

        return $this->header().implode("\n", $blocks);
    }

    private function header(): string
    {
        return <<<'TS'
            /**
             * ARCHIVO GENERADO — no lo edites a mano.
             *
             * Espejo de los enums de `app/Enums` con respaldo de string: mismos
             * valores y mismo orden. Se regenera con `php artisan enums:typescript`
             * y la suite falla si queda desactualizado.
             */


            TS;
    }

    private function block(ReflectionEnum $enum): string
    {
        $name = $enum->getShortName();
        $constant = Str::upper(Str::snake($name)).'_VALUES';
        $values = array_map(
            static fn (\ReflectionEnumBackedCase $case): string => "    '".$case->getBackingValue()."',",
            $enum->getCases(),
        );

        return implode("\n", [
            '/** `'.$enum->getName().'` */',
            'export const '.$constant.' = [',
            ...$values,
            '] as const;',
            '',
            'export type '.$name.' = (typeof '.$constant.')[number];',
            '',
        ]);
    }

    /**
     * Todos los enums con respaldo de string de `app/Enums`, en orden alfabético.
     *
     * WHY: se vierten todos y no una lista marcada a mano. Una lista es otra
     * cosa que se olvida de actualizar, y el costo de exportar un enum que la UI
     * todavía no usa es un tipo muerto que Vite ni siquiera empaqueta.
     *
     * @return array<int, ReflectionEnum>
     */
    private function enums(): array
    {
        $files = Finder::create()->files()->in(base_path(self::SOURCE))->name('*.php')->sortByName();

        $enums = [];

        foreach ($files as $file) {
            /** @var SplFileInfo $file */
            $class = 'App\\Enums\\'.$file->getBasename('.php');

            if (! enum_exists($class)) {
                continue;
            }

            $enum = new ReflectionEnum($class);

            if ($enum->getBackingType()?->getName() === 'string') {
                $enums[] = $enum;
            }
        }

        return $enums;
    }
}
