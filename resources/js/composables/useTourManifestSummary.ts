import type { Ref } from 'vue';
import { ref } from 'vue';
import { index as adminManifest } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourPassengerController';
import type {
    PassengerManifestResponse,
    PassengerManifestSummary,
} from '@/types/passenger';

export type UseTourManifestSummaryReturn = {
    summary: Ref<PassengerManifestSummary | null>;
    loading: Ref<boolean>;
    load: () => Promise<void>;
};

/**
 * El resumen agregado de la planilla del tour: cuántos pasajeros hay, cuántos
 * deben y cuánto.
 *
 * WHY: la edición necesita esas tres cifras para el contador de la pestaña y
 * para la tarjeta de impacto ANTES de que nadie abra la planilla, y son
 * exactamente las que el `meta.summary` del endpoint ya calcula. Se pide una
 * página mínima porque lo que interesa es el `meta`, no las filas — la planilla
 * completa la trae `usePassengerManifest` cuando se abre la pestaña.
 */
export function useTourManifestSummary(
    tourId: number,
): UseTourManifestSummaryReturn {
    const summary = ref<PassengerManifestSummary | null>(null);
    const loading = ref(false);

    async function load(): Promise<void> {
        loading.value = true;

        try {
            const response = await fetch(
                adminManifest.url(tourId, { query: { per_page: 10, page: 1 } }),
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const payload =
                (await response.json()) as PassengerManifestResponse;
            summary.value = payload.meta.summary;
        } catch {
            // Sin resumen no se dibuja el bloque: un cero inventado se leería
            // como «este tour no tiene pasajeros».
            summary.value = null;
        } finally {
            loading.value = false;
        }
    }

    return { summary, loading, load };
}
