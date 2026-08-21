import type { Ref } from 'vue';
import { onScopeDispose, ref } from 'vue';
import geocode from '@/actions/App/Http/Controllers/Api/V1/Admin/GeocodeController';
import { translate } from '@/composables/useTranslations';
import type { GeocodedPlace, GeocodeResponse } from '@/types/geocoding';

export type UseGeocoderReturn = {
    results: Ref<GeocodedPlace[]>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    /** Lanza la búsqueda con retardo; teclear no dispara una petición por letra. */
    search: (term: string) => void;
    clear: () => void;
};

const DEBOUNCE_MS = 400;
const MIN_LENGTH = 3;

/**
 * Buscador de direcciones del editor de ruta.
 *
 * Va contra nuestro propio endpoint, no contra el servicio de mapas: el
 * backend identifica la petición, respeta el límite del proveedor y cachea.
 * Aquí solo se cuida de no mandar una petición por tecla y de cancelar la
 * anterior cuando llega otra —si no, la respuesta lenta de «Sal» pisaba la de
 * «Salento»—.
 */
export function useGeocoder(): UseGeocoderReturn {
    const results = ref<GeocodedPlace[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;

    function cancelPending(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        controller?.abort();
        controller = null;
    }

    function clear(): void {
        cancelPending();
        results.value = [];
        error.value = null;
        loading.value = false;
    }

    async function run(term: string): Promise<void> {
        controller = new AbortController();
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch(geocode.url({ query: { q: term } }), {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            results.value = ((await response.json()) as GeocodeResponse).data;

            if (results.value.length === 0) {
                error.value = translate(
                    'Sin resultados. Prueba con la ciudad o marca el punto en el mapa.',
                );
            }
        } catch (thrown) {
            if (
                thrown instanceof DOMException &&
                thrown.name === 'AbortError'
            ) {
                return;
            }

            results.value = [];
            error.value = translate(
                'No pudimos buscar la dirección. Marca el punto en el mapa.',
            );
        } finally {
            loading.value = false;
        }
    }

    function search(term: string): void {
        cancelPending();

        const trimmed = term.trim();

        if (trimmed.length < MIN_LENGTH) {
            results.value = [];
            error.value = null;

            return;
        }

        timer = setTimeout(() => void run(trimmed), DEBOUNCE_MS);
    }

    onScopeDispose(cancelPending);

    return { results, loading, error, search, clear };
}
