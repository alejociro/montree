import { watchEffect } from 'vue';
import { useTenant } from '@/composables/useTenant';

const VAR_PRIMARY = '--primary';
const VAR_PRIMARY_FOREGROUND = '--primary-foreground';
const VAR_SECONDARY = '--secondary';
const VAR_RING = '--ring';
const VAR_SIDEBAR_PRIMARY = '--sidebar-primary';
const VAR_SIDEBAR_RING = '--sidebar-ring';

const TRACKED_VARS = [
    VAR_PRIMARY,
    VAR_PRIMARY_FOREGROUND,
    VAR_SECONDARY,
    VAR_RING,
    VAR_SIDEBAR_PRIMARY,
    VAR_SIDEBAR_RING,
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
 * Applies the tenant primary/secondary colors as CSS custom properties on
 * `:root`, so every shadcn token that consumes `--primary` / `--secondary`
 * picks up the tenant branding automatically.
 *
 * Backend already returns colors as raw HSL triplets (e.g. `"142 76% 36%"`),
 * so we wrap them in `hsl(...)` before assigning, matching the format used
 * by the rest of the design tokens in `app.css`.
 *
 * When no tenant is resolved, original defaults are restored.
 */
export function useTenantBranding(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const { configuration } = useTenant();
    const root = document.documentElement;

    watchEffect(() => {
        const defaults = captureDefaults(root);
        const config = configuration.value;

        if (!config) {
            resetToDefaults(root, defaults);

            return;
        }

        if (config.primary_color_hsl) {
            const hsl = `hsl(${config.primary_color_hsl})`;
            root.style.setProperty(VAR_PRIMARY, hsl);
            root.style.setProperty(VAR_RING, hsl);
            root.style.setProperty(VAR_SIDEBAR_PRIMARY, hsl);
            root.style.setProperty(VAR_SIDEBAR_RING, hsl);
        } else {
            root.style.setProperty(VAR_PRIMARY, defaults[VAR_PRIMARY]);
            root.style.setProperty(VAR_RING, defaults[VAR_RING]);
            root.style.setProperty(
                VAR_SIDEBAR_PRIMARY,
                defaults[VAR_SIDEBAR_PRIMARY],
            );
            root.style.setProperty(
                VAR_SIDEBAR_RING,
                defaults[VAR_SIDEBAR_RING],
            );
        }

        if (config.secondary_color_hsl) {
            root.style.setProperty(
                VAR_SECONDARY,
                `hsl(${config.secondary_color_hsl})`,
            );
        } else {
            root.style.setProperty(VAR_SECONDARY, defaults[VAR_SECONDARY]);
        }
    });
}
