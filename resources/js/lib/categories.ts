import { translate } from '@/composables/useTranslations';

/**
 * Catalogo de categorias que `config/montree.php` siembra en cada tenant nuevo. Es copy
 * de la aplicacion, no dato de la agencia: aparece igual en todas, asi que se traduce
 * en el punto de render como el resto de los catalogos estaticos (plan.md §5).
 *
 * La lista existe ademas para `TranslationCatalogTest`, que solo mira `app/`, `resources/js`
 * y `resources/views`: sin el literal aca, las entradas de `lang/en.json` quedarian
 * marcadas como huerfanas porque su unica fuente es un archivo de `config/`.
 */
export const DEFAULT_CATEGORY_NAMES = [
    'Senderismo',
    'Aventura',
    'Cultural',
    'Gastronomía',
    'Avistamiento',
] as const;

/**
 * Etiqueta visible de una categoria. Las que crea una agencia salen tal cual: `translate()`
 * devuelve la clave cuando no hay entrada en el catalogo.
 */
export function categoryLabel(name: string): string {
    return translate(name);
}
