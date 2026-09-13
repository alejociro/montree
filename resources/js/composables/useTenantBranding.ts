import { onScopeDispose, watchEffect } from 'vue';
import { useTenant } from '@/composables/useTenant';
import { readableInkForTriplet, textSafeHsl } from '@/lib/color';

const VAR_PRIMARY = '--primary';
const VAR_PRIMARY_FOREGROUND = '--primary-foreground';
const VAR_PRIMARY_READABLE = '--primary-readable';
const VAR_SECONDARY = '--secondary';
const VAR_SECONDARY_FOREGROUND = '--secondary-foreground';
const VAR_SECONDARY_READABLE = '--secondary-readable';
const VAR_RING = '--ring';

/**
 * WHY solo estas siete: todo lo demás que dependía del color de la agencia
 * —los `--sidebar-*`, las superficies suaves `*-soft`— se deriva ahora en
 * `app.css` con `color-mix()` sobre `--primary` / `--secondary`. Así el modo
 * oscuro sale gratis (la mezcla es contra `--background`, que ya cambia) y hay
 * una sola definición de «qué tan claro es el tinte», en CSS, no repartida
 * entre CSS y este archivo.
 */
const TRACKED_VARS = [
    VAR_PRIMARY,
    VAR_PRIMARY_FOREGROUND,
    VAR_PRIMARY_READABLE,
    VAR_SECONDARY,
    VAR_SECONDARY_FOREGROUND,
    VAR_SECONDARY_READABLE,
    VAR_RING,
] as const;

type CachedDefaults = Record<(typeof TRACKED_VARS)[number], string>;

let cachedDefaults: CachedDefaults | null = null;

function captureDefaults(root: HTMLElement): CachedDefaults {
    if (cachedDefaults) {
        return cachedDefaults;
    }

    const styles = getComputedStyle(root);
    const snapshot = {} as CachedDefaults;

    for (const name of TRACKED_VARS) {
        snapshot[name] = styles.getPropertyValue(name).trim();
    }

    cachedDefaults = snapshot;

    return snapshot;
}

function resetToDefaults(root: HTMLElement, defaults: CachedDefaults): void {
    for (const name of TRACKED_VARS) {
        const value = defaults[name];

        if (value) {
            root.style.setProperty(name, value);
        } else {
            root.style.removeProperty(name);
        }
    }
}

/**
 * Color de fondo real de la página, ya resuelto por el navegador.
 *
 * WHY no leer `--background`: las custom properties se sustituyen tarde, así
 * que `getPropertyValue('--background')` devuelve literalmente
 * `var(--brand-cream)`. El `background-color` calculado del `body` sí es un
 * `rgb()` — y además cambia solo cuando entra el modo oscuro.
 */
function pageBackground(): string {
    const computed = getComputedStyle(document.body).backgroundColor;

    return computed === '' || computed === 'rgba(0, 0, 0, 0)'
        ? '#f6f1e4'
        : computed;
}

/**
 * Aplica el primario y el secundario del tenant como custom properties de
 * `:root`, para que cada token del design system que los consuma tome la marca
 * de la agencia automáticamente.
 *
 * El backend ya manda los colores como tripletes HSL crudos (`"142 76% 36%"`),
 * así que aquí solo se envuelven en `hsl(...)`. Además de eso se calculan dos
 * cosas que el backend no puede saber porque dependen del tema en pantalla:
 *
 * - `*-foreground`: la tinta que se lee ENCIMA del color de marca (fondo de un
 *   botón, de un badge, de una card).
 * - `*-readable`: la variante del color de marca que se lee COMO texto sobre el
 *   fondo de la página, para el color de marca en enlaces y titulares.
 *
 * Sin tenant resuelto se restauran los valores originales del design system.
 */
export function useTenantBranding(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const { configuration } = useTenant();
    const root = document.documentElement;

    /**
     * El cálculo de contraste depende del fondo, y el fondo cambia al alternar
     * el tema. Sin este observador, una agencia con el primario claro quedaba
     * con el texto de marca ilegible hasta recargar.
     */
    let themeVersion = 0;
    const observer = new MutationObserver(() => {
        themeVersion += 1;
    });

    // Solo `class`: el tema se alterna con `.dark` en el `<html>`. Observar
    // `style` haría un bucle, porque este mismo efecto escribe ahí.
    observer.observe(root, { attributes: true, attributeFilter: ['class'] });

    onScopeDispose(() => observer.disconnect());

    watchEffect(() => {
        void themeVersion;

        const defaults = captureDefaults(root);
        const config = configuration.value;

        if (!config) {
            resetToDefaults(root, defaults);

            return;
        }

        const background = pageBackground();

        if (config.primary_color_hsl) {
            root.style.setProperty(
                VAR_PRIMARY,
                `hsl(${config.primary_color_hsl})`,
            );
            root.style.setProperty(
                VAR_PRIMARY_FOREGROUND,
                readableInkForTriplet(config.primary_color_hsl),
            );
            root.style.setProperty(
                VAR_PRIMARY_READABLE,
                textSafeHsl(config.primary_color_hsl, background),
            );
            root.style.setProperty(
                VAR_RING,
                `hsl(${config.primary_color_hsl})`,
            );
        } else {
            root.style.setProperty(VAR_PRIMARY, defaults[VAR_PRIMARY]);
            root.style.setProperty(
                VAR_PRIMARY_FOREGROUND,
                defaults[VAR_PRIMARY_FOREGROUND],
            );
            root.style.setProperty(
                VAR_PRIMARY_READABLE,
                defaults[VAR_PRIMARY_READABLE],
            );
            root.style.setProperty(VAR_RING, defaults[VAR_RING]);
        }

        if (config.secondary_color_hsl) {
            root.style.setProperty(
                VAR_SECONDARY,
                `hsl(${config.secondary_color_hsl})`,
            );
            root.style.setProperty(
                VAR_SECONDARY_FOREGROUND,
                readableInkForTriplet(config.secondary_color_hsl),
            );
            root.style.setProperty(
                VAR_SECONDARY_READABLE,
                textSafeHsl(config.secondary_color_hsl, background),
            );
        } else {
            root.style.setProperty(VAR_SECONDARY, defaults[VAR_SECONDARY]);
            root.style.setProperty(
                VAR_SECONDARY_FOREGROUND,
                defaults[VAR_SECONDARY_FOREGROUND],
            );
            root.style.setProperty(
                VAR_SECONDARY_READABLE,
                defaults[VAR_SECONDARY_READABLE],
            );
        }
    });
}
