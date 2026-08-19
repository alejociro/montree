<?php

declare(strict_types=1);

namespace Tests\Unit\Locale;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Red de seguridad del feature multilanguage-es-en: recorre el código fuente,
 * extrae cada clave que se traduce en tiempo de ejecución y falla si alguna no
 * está en `lang/en.json`.
 *
 * Sin este test la cobertura se degrada en el primer PR que agregue una pantalla:
 * una clave sin entrada no rompe nada visible — se ve en español dentro de una
 * pantalla en inglés, que es justo lo que este feature vino a eliminar.
 */
class TranslationCatalogTest extends TestCase
{
    /**
     * Claves que se resuelven contra los archivos PHP del framework
     * (`lang/es/validation.php`, `auth.php`, `passwords.php`) o contra el catálogo
     * en inglés que Laravel ya trae, y por eso no viven en `lang/en.json`.
     *
     * @var list<string>
     */
    private const FRAMEWORK_KEYS = [
        'The provided password was incorrect.',
        'The provided two factor authentication code was invalid.',
        'The provided two factor recovery code was invalid.',
    ];

    public function test_every_translated_key_exists_in_the_english_catalog(): void
    {
        $catalog = json_decode((string) file_get_contents($this->basePath('lang/en.json')), true);

        $this->assertIsArray($catalog);

        $missing = array_values(array_filter(
            $this->sourceKeys(),
            fn (string $key): bool => ! array_key_exists($key, $catalog)
                && ! in_array($key, self::FRAMEWORK_KEYS, true)
                // Clave punteada (`passwords.sent`): la resuelve un archivo PHP del
                // framework, no el catalogo JSON.
                && preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) !== 1,
        ));

        $this->assertSame([], $missing, sprintf(
            "Hay %d claves sin traducción en lang/en.json:\n- %s",
            count($missing),
            implode("\n- ", array_slice($missing, 0, 20)),
        ));
    }

    public function test_english_catalog_has_no_orphan_entries(): void
    {
        $catalog = json_decode((string) file_get_contents($this->basePath('lang/en.json')), true);

        $this->assertIsArray($catalog);

        // WHY: no basta con las claves que llegan dentro de un `t(...)`. Los catalogos
        // estaticos (`config/navigation.ts`, `PermissionCatalog`, los breadcrumbs de
        // `defineOptions`) declaran el texto en un lado y lo traducen en el render, asi
        // que aca alcanza con que la clave exista como literal en alguna fuente.
        $corpus = '';

        foreach ($this->sourceFiles() as $file) {
            $corpus .= (string) file_get_contents($file);
        }

        $orphans = array_values(array_filter(
            array_keys($catalog),
            fn (string $key): bool => ! str_contains($corpus, "'".str_replace("'", "\\'", $key)."'")
                && ! str_contains($corpus, '"'.$key.'"'),
        ));

        $this->assertSame([], $orphans, sprintf(
            "Hay %d entradas en lang/en.json que ya nadie usa:\n- %s",
            count($orphans),
            implode("\n- ", array_slice($orphans, 0, 20)),
        ));
    }

    /**
     * @return list<string>
     */
    private function sourceKeys(): array
    {
        $keys = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            $pattern = str_ends_with($file, '.php')
                ? "/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/"
                : "/(?:\\\$t|\\\$tc|\bt|\btChoice|\btranslate|\btranslateChoice)\(\s*'((?:[^'\\\\]|\\\\.)*)'/";

            preg_match_all($pattern, $contents, $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);

                // `emit('update:modelValue')` cae en el mismo patron que `t('...')`:
                // no es copy, es el nombre de un evento.
                if ($key === '' || str_starts_with($key, 'update:')) {
                    continue;
                }

                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'bootstrap', 'resources/js', 'resources/views'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->basePath($dir), RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                $path = $file->getPathname();

                // Wayfinder regenera estas carpetas: no llevan copy y no se versionan.
                if (str_contains($path, '/resources/js/routes/') || str_contains($path, '/resources/js/actions/')) {
                    continue;
                }

                if (preg_match('/\.(php|vue|ts)$/', $path) === 1) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    private function basePath(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }
}
