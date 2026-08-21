import type { Ref } from 'vue';
import { ref, watch } from 'vue';
import guideAvailability from '@/actions/App/Http/Controllers/Api/V1/Admin/GuideAvailabilityController';
import { translate } from '@/composables/useTranslations';
import type {
    BusyBlock,
    DepartureRange,
    GuideAvailability,
    GuideAvailabilityResponse,
} from '@/types/guide-availability';

export type UseGuideAvailabilityReturn = {
    guides: Ref<GuideAvailability[]>;
    loading: Ref<boolean>;
    error: Ref<string | null>;
    /** El bloque que le impide a ese guía tomar el rango, o `null` si está libre. */
    blockFor: (guideId: number) => BusyBlock | null;
    /** El motivo tal como se muestra: «Ocupado 12–14 sep · Valle de Cocora». */
    reasonFor: (guideId: number) => string | null;
    reload: () => Promise<void>;
};

const MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic',
];

/**
 * WHY: el motivo se arma en el cliente con las mismas abreviaturas que el
 * servidor. Es la misma frase que devolvería el 422, y así el select y el error
 * no se contradicen.
 */
export function formatBusyRange(block: BusyBlock): string {
    const [, fromMonth, fromDay] = block.from.split('-').map(Number);
    const [, toMonth, toDay] = block.to.split('-').map(Number);

    const range =
        block.from === block.to
            ? `${fromDay} ${MONTHS[fromMonth - 1]}`
            : fromMonth === toMonth
              ? `${fromDay}–${toDay} ${MONTHS[toMonth - 1]}`
              : `${fromDay} ${MONTHS[fromMonth - 1]} – ${toDay} ${MONTHS[toMonth - 1]}`;

    return `${range} · ${block.tour_name}`;
}

/**
 * Los días que cada guía ya tiene tomados dentro del rango de la salida (D9).
 * El select no ofrece lo que la regla del servidor va a rechazar, pero la regla
 * sigue estando allá: esto es cortesía de la UI, no la validación.
 */
export function useGuideAvailability(
    range: Ref<DepartureRange | null>,
    excludeTourDateId: Ref<number | null>,
): UseGuideAvailabilityReturn {
    const guides = ref<GuideAvailability[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    function blockFor(guideId: number): BusyBlock | null {
        return (
            guides.value.find((guide) => guide.id === guideId)?.busy[0] ?? null
        );
    }

    function reasonFor(guideId: number): string | null {
        const block = blockFor(guideId);

        return block === null
            ? null
            : `${translate('Ocupado')} ${formatBusyRange(block)}`;
    }

    async function reload(): Promise<void> {
        const current = range.value;

        if (current === null) {
            guides.value = [];

            return;
        }

        loading.value = true;
        error.value = null;

        const query: Record<string, string | number> = {
            from: current.from,
            to: current.to,
        };

        if (excludeTourDateId.value !== null) {
            query.exclude_tour_date_id = excludeTourDateId.value;
        }

        try {
            const response = await fetch(guideAvailability.url({ query }), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const payload =
                (await response.json()) as GuideAvailabilityResponse;
            guides.value = payload.data;
        } catch {
            // Sin disponibilidad el select sigue funcionando: ofrece a todos y
            // el servidor rechaza al ocupado. Peor sería bloquear el formulario.
            guides.value = [];
            error.value = translate(
                'No pudimos consultar la agenda de los guías.',
            );
        } finally {
            loading.value = false;
        }
    }

    watch([range, excludeTourDateId], () => void reload(), { immediate: true });

    return { guides, loading, error, blockFor, reasonFor, reload };
}
