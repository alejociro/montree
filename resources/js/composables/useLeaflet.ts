import type { LeafletNamespace } from '@/types/leaflet';

const LEAFLET_VERSION = '1.9.4';
const STYLE_URL = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`;
const SCRIPT_URL = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`;
const STYLE_INTEGRITY =
    'sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H';
const SCRIPT_INTEGRITY =
    'sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH';

let pendingLoad: Promise<LeafletNamespace> | null = null;

function injectStylesheet(): void {
    if (document.querySelector(`link[href="${STYLE_URL}"]`) !== null) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = STYLE_URL;
    link.integrity = STYLE_INTEGRITY;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
}

function injectScript(): Promise<LeafletNamespace> {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${SCRIPT_URL}"]`,
        );

        const settle = (): void => {
            if (window.L === undefined) {
                reject(new Error('Leaflet no quedó disponible en window.L'));

                return;
            }

            resolve(window.L);
        };

        if (existing !== null) {
            existing.addEventListener('load', settle, { once: true });
            existing.addEventListener(
                'error',
                () => reject(new Error('No se pudo cargar Leaflet')),
                { once: true },
            );

            return;
        }

        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.integrity = SCRIPT_INTEGRITY;
        script.crossOrigin = 'anonymous';
        script.async = true;
        script.addEventListener('load', settle, { once: true });
        script.addEventListener(
            'error',
            () => reject(new Error('No se pudo cargar Leaflet')),
            { once: true },
        );
        document.head.appendChild(script);
    });
}

/**
 * Carga Leaflet bajo demanda desde el CDN (una sola vez por sesión).
 * Se hace así, y no con un import de npm, porque agregar dependencias al
 * proyecto requiere PR aprobado según la constitución (§1).
 */
export function loadLeaflet(): Promise<LeafletNamespace> {
    if (window.L !== undefined) {
        return Promise.resolve(window.L);
    }

    if (pendingLoad === null) {
        injectStylesheet();
        pendingLoad = injectScript().catch((error: unknown) => {
            pendingLoad = null;

            throw error;
        });
    }

    return pendingLoad;
}
