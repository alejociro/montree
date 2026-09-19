import {
    Bike,
    Binoculars,
    Camera,
    Compass,
    Fish,
    Flame,
    Footprints,
    Map,
    Mountain,
    Palette,
    Sailboat,
    Sun,
    Tent,
    TreePine,
    Utensils,
    Waves,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { translate } from '@/composables/useTranslations';
import type { CategoryIcon } from '@/types/enums.generated';

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

/**
 * Componente Lucide de cada icono del enum `App\Enums\CategoryIcon`. El valor del
 * enum ES el nombre del icono, pero el mapa se escribe igual: importar por nombre
 * dinamico deja a Vite empaquetando el paquete entero.
 */
const CATEGORY_ICONS: Record<CategoryIcon, Component> = {
    mountain: Mountain,
    compass: Compass,
    palette: Palette,
    utensils: Utensils,
    binoculars: Binoculars,
    bike: Bike,
    waves: Waves,
    tent: Tent,
    'tree-pine': TreePine,
    camera: Camera,
    fish: Fish,
    sailboat: Sailboat,
    footprints: Footprints,
    flame: Flame,
    sun: Sun,
    map: Map,
};

/** Icono de una categoria; `Compass` para las que no eligieron ninguno. */
export function categoryIconComponent(icon: string | null): Component {
    return (icon !== null ? CATEGORY_ICONS[icon as CategoryIcon] : undefined) ?? Compass;
}
