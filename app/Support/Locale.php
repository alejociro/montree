<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Lectura del catálogo de idiomas (`config('montree.locales')`) y de los archivos
 * `lang/{locale}.json`. Es el único lugar que sabe dónde viven las traducciones:
 * el middleware, el Form Request y HandleInertiaRequests preguntan acá.
 */
final class Locale
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var array<string, array{name: string, native: string}> $locales */
        $locales = config('montree.locales', []);

        return array_keys($locales);
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    public static function default(): string
    {
        $default = (string) config('app.locale');

        return self::isSupported($default) ? $default : (self::supported()[0] ?? 'es');
    }

    /**
     * Catálogo para el selector del frontend (contracts.md §Props compartidas).
     *
     * @return list<array{code: string, name: string, native: string}>
     */
    public static function options(): array
    {
        /** @var array<string, array{name: string, native: string}> $locales */
        $locales = config('montree.locales', []);

        return array_values(array_map(
            fn (string $code, array $meta): array => [
                'code' => $code,
                'name' => __($meta['name']),
                'native' => $meta['native'],
            ],
            array_keys($locales),
            $locales,
        ));
    }

    /**
     * Mapa plano de `lang/{locale}.json`.
     *
     * WHY: en español el archivo está casi vacío a propósito — la clave ES el texto en
     * español (plan.md §5), así que `__()` devuelve la clave y no hace falta entrada.
     *
     * @return array<string, string>
     */
    public static function translations(string $locale): array
    {
        $load = function () use ($locale): array {
            $path = lang_path("{$locale}.json");

            if (! File::exists($path)) {
                return [];
            }

            /** @var array<string, string>|null $decoded */
            $decoded = json_decode((string) File::get($path), true);

            return is_array($decoded) ? $decoded : [];
        };

        if (app()->environment('local', 'testing')) {
            return $load();
        }

        /** @var array<string, string> $cached */
        $cached = Cache::rememberForever("translations.{$locale}", $load);

        return $cached;
    }
}
