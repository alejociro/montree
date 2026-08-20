import 'leaflet/dist/leaflet.css';
import type { LeafletNamespace } from '@/types/leaflet';

let pendingLoad: Promise<LeafletNamespace> | null = null;

/**
 * Carga Leaflet bajo demanda (una sola vez por sesión). El import es dinámico
 * porque la librería toca `window` al evaluarse: solo puede correr en el cliente.
 */
export function loadLeaflet(): Promise<LeafletNamespace> {
    if (pendingLoad === null) {
        pendingLoad = import('leaflet').catch((error: unknown) => {
            pendingLoad = null;

            throw error;
        });
    }

    return pendingLoad;
}
