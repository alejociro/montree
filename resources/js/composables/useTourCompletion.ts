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
     * El checklist que emitió el servidor para este tour (`publish_checklist`).
     * De ahí salen el orden, las etiquetas y —sobre todo— qué bloquea; `done`
     * se recalcula aquí sobre lo que hay escrito, que es lo único que el
     * servidor no puede saber todavía. En «crear» no existe: el tour aún no
     * está guardado y se cae a la lista local.
     */
    serverRequirements?: TourPublishRequirement[];
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
 * dos vistas de la misma verdad, y esa verdad son las reglas del backend. Desde
 * la Fase 7 la lista la emite el servidor en `tour.publish_checklist`
 * (`App\Services\Tour\TourPublishChecklist`, la misma que consulta
 * `ChangeTourStatusAction`): de ahí salen el orden, las etiquetas y qué
 * bloquea. Acá solo se recalcula `done` sobre lo que hay escrito sin guardar,
 * que es lo único que el servidor todavía no puede saber.
 *
 * La lista local sobrevive para «crear», donde el tour aún no existe y no hay
 * respuesta del servidor de la que partir. Es un espejo de la de PHP; si las
 * dos se separan, manda la del servidor en cuanto el borrador se guarda.
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

    const localDone = computed<Record<string, boolean>>(() => ({
        general: generalDone.value,
        summary: filled(payload.value.short_description),
        pricing: pricingDone.value,
        image: images.value > 0,
        guide: guideDone.value,
        stops: hasPickup.value && hasDrop.value,
    }));

    const localRequirements = computed<TourPublishRequirement[]>(() => [
        {
            id: 'general',
            label: t('Nombre y descripción'),
            done: localDone.value.general,
            blocking: true,
        },
        {
            id: 'summary',
            label: t('Resumen corto'),
            done: localDone.value.summary,
            blocking: true,
        },
        {
            id: 'pricing',
            label: t('Precio, cupo y duración'),
            done: localDone.value.pricing,
            blocking: true,
        },
        {
            id: 'image',
            label: t('Al menos una imagen'),
            done: localDone.value.image,
            blocking: true,
            hint: imageHint(),
        },
        {
            id: 'guide',
            label: t('Guía por defecto'),
            done: localDone.value.guide,
            blocking: true,
        },
        {
            id: 'stops',
            label: t('Parada de recogida y de regreso'),
            done: localDone.value.stops,
            blocking: false,
        },
    ]);

    function imageHint(): string | undefined {
        return options.pendingCreation === true
            ? t('Se cargan cuando el borrador ya existe.')
            : undefined;
    }

    /**
     * El servidor manda en el reparto bloqueante/recomendado; el formulario
     * manda en si la condición ya se cumple con lo que hay escrito. Una
     * condición que el servidor no conoce no se inventa aquí.
     */
    const requirements = computed<TourPublishRequirement[]>(() => {
        const fromServer = options.serverRequirements;

        if (fromServer === undefined || fromServer.length === 0) {
            return localRequirements.value;
        }

        return fromServer.map((requirement) => ({
            ...requirement,
            done: localDone.value[requirement.id] ?? requirement.done,
            hint: requirement.id === 'image' ? imageHint() : requirement.hint,
        }));
    });

    const blockingCount = computed<number>(
        () =>
            requirements.value.filter(
                (requirement) => requirement.blocking && !requirement.done,
            ).length,
    );

    return { steps, requirements, blockingCount };
}
