import type { ComputedRef, Ref } from 'vue';
import { computed, unref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatNumber } from '@/lib/format';
import type {
    TourFormPayload,
    TourFormStep,
    TourPublishRequirement,
} from '@/types/tour';

export type UseTourCompletionOptions = {
    /** Imágenes ya cargadas en el tour; en «crear» todavía no existe ninguna. */
    imagesCount: Ref<number> | number;
    /**
     * El tour todavía no está guardado, así que la galería no se puede tocar
     * aún. Cambia el texto de la condición, no la condición.
     */
    pendingCreation?: boolean;
};

export type UseTourCompletionReturn = {
    steps: ComputedRef<TourFormStep[]>;
    requirements: ComputedRef<TourPublishRequirement[]>;
    /** Condiciones que el backend rechaza y siguen sin cumplirse. */
    blockingCount: ComputedRef<number>;
};

function filled(value: string): boolean {
    return value.trim() !== '';
}

function positiveNumber(value: string): boolean {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) && parsed > 0;
}

/**
 * Estado de completitud del formulario del tour: lo que alimenta el riel de
 * progreso y el checklist «Para publicar».
 *
 * WHY: vive fuera de los componentes a propósito. El riel y el checklist son
 * dos vistas de la misma verdad, y esa verdad son las reglas del backend —
 * `StoreTourRequest`/`UpdateTourRequest` para lo que ni siquiera deja guardar,
 * y `ChangeTourStatusAction` para lo que impide activar (imagen y guía por
 * defecto). Las paradas de recogida y regreso NO bloquean nada allá, así que
 * acá se marcan como recomendadas (D7): endurecerlas dejaría en borrador a
 * tours que hoy están activos.
 */
export function useTourCompletion(
    payload: Ref<TourFormPayload>,
    options: UseTourCompletionOptions,
): UseTourCompletionReturn {
    const { t } = useTranslations();

    const images = computed<number>(() => unref(options.imagesCount));

    const generalDone = computed<boolean>(
        () => filled(payload.value.name) && filled(payload.value.description),
    );

    const pricingDone = computed<boolean>(
        () =>
            positiveNumber(payload.value.base_price) &&
            payload.value.default_capacity >= 1 &&
            payload.value.duration_hours >= 1,
    );

    const detailDone = computed<boolean>(
        () =>
            payload.value.includes.length > 0 ||
            payload.value.requirements.length > 0,
    );

    const hasPickup = computed<boolean>(() =>
        payload.value.stops.some((stop) => stop.kind === 'pickup'),
    );

    const hasDrop = computed<boolean>(() =>
        payload.value.stops.some((stop) => stop.kind === 'drop'),
    );

    const guideDone = computed<boolean>(
        () => payload.value.default_guide_id !== null,
    );

    const steps = computed<TourFormStep[]>(() => [
        {
            id: 'general',
            anchor: 'tour-block-general',
            label: t('Información general'),
            hint: generalDone.value
                ? payload.value.name
                : t('Nombre y descripción'),
            done: generalDone.value,
        },
        {
            id: 'pricing',
            anchor: 'tour-block-pricing',
            label: t('Precio y cupo'),
            hint: pricingDone.value
                ? t(':price · :capacity pers.', {
                      price: formatCurrency(
                          payload.value.base_price,
                          payload.value.currency,
                      ),
                      capacity: formatNumber(payload.value.default_capacity),
                  })
                : t('Precio, cupo y duración'),
            done: pricingDone.value,
        },
        {
            id: 'detail',
            anchor: 'tour-block-detail',
            label: t('Detalle'),
            hint: detailDone.value
                ? t(':includes incluye · :requirements requisitos', {
                      includes: formatNumber(payload.value.includes.length),
                      requirements: formatNumber(
                          payload.value.requirements.length,
                      ),
                  })
                : t('Incluye / requisitos'),
            done: detailDone.value,
        },
        {
            id: 'route',
            anchor: 'tour-block-route',
            label: t('Ruta y mapa'),
            hint:
                payload.value.stops.length === 0
                    ? t('Sin paradas')
                    : t(':count paradas', {
                          count: formatNumber(payload.value.stops.length),
                      }),
            done: payload.value.stops.length > 0,
        },
        {
            id: 'gallery',
            anchor: 'tour-block-gallery',
            label: t('Galería'),
            hint:
                images.value > 0
                    ? t(':count imágenes', {
                          count: formatNumber(images.value),
                      })
                    : options.pendingCreation === true
                      ? t('Se carga al guardar')
                      : t('Falta la portada'),
            done: images.value > 0,
        },
    ]);

    const requirements = computed<TourPublishRequirement[]>(() => [
        {
            id: 'general',
            label: t('Nombre y descripción'),
            done: generalDone.value,
            blocking: true,
        },
        {
            id: 'pricing',
            label: t('Precio, cupo y duración'),
            done: pricingDone.value,
            blocking: true,
        },
        {
            id: 'image',
            label: t('Al menos una imagen'),
            done: images.value > 0,
            blocking: true,
            hint:
                options.pendingCreation === true
                    ? t('Se cargan cuando el borrador ya existe.')
                    : undefined,
        },
        {
            id: 'guide',
            label: t('Guía por defecto'),
            done: guideDone.value,
            blocking: true,
        },
        {
            id: 'summary',
            label: t('Resumen corto'),
            done: filled(payload.value.short_description),
            blocking: false,
        },
        {
            id: 'stops',
            label: t('Parada de recogida y de regreso'),
            done: hasPickup.value && hasDrop.value,
            blocking: false,
        },
    ]);

    const blockingCount = computed<number>(
        () =>
            requirements.value.filter(
                (requirement) => requirement.blocking && !requirement.done,
            ).length,
    );

    return { steps, requirements, blockingCount };
}
