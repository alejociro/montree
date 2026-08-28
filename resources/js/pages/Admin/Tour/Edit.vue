<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CircleCheck,
    ExternalLink,
    FileText,
    Loader2,
    PauseCircle,
    Trash2,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexPage,
    show as showPage,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import CancelTourDateController from '@/actions/App/Http/Controllers/Api/V1/Admin/CancelTourDateController';
import {
    destroy as destroyTour,
    update as updateTour,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import { destroy as destroyDate } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateController';
import changeStatus from '@/actions/App/Http/Controllers/Api/V1/Admin/TourStatusController';
import { show as publicTour } from '@/actions/App/Http/Controllers/PublicTourPageController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import PickupChangeNotice from '@/components/molecules/PickupChangeNotice.vue';
import StickySaveBar from '@/components/molecules/StickySaveBar.vue';
import TourTabs from '@/components/molecules/TourTabs.vue';
import type { TourTabItem } from '@/components/molecules/TourTabs.vue';
import TourDateFormDialog from '@/components/organisms/TourDateFormDialog.vue';
import TourDeparturesTable from '@/components/organisms/TourDeparturesTable.vue';
import TourForm from '@/components/organisms/TourForm.vue';
import TourImageUploader from '@/components/organisms/TourImageUploader.vue';
import TourImpactCard from '@/components/organisms/TourImpactCard.vue';
import TourPassengerPreview from '@/components/organisms/TourPassengerPreview.vue';
import TourProgressRail from '@/components/organisms/TourProgressRail.vue';
import TourPublishChecklist from '@/components/organisms/TourPublishChecklist.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import TourStatusRailCard from '@/components/organisms/TourStatusRailCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenuItem,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { usePermissions } from '@/composables/usePermissions';
import { useTourCompletion } from '@/composables/useTourCompletion';
import { useTourDepartures } from '@/composables/useTourDepartures';
import { useTourManifestSummary } from '@/composables/useTourManifestSummary';
import { useTranslations } from '@/composables/useTranslations';
import { applyFormValue } from '@/lib/form-errors';
import { formatRelativeDate } from '@/lib/format';
import { tourStopDraftsFrom, tourStopsPayload } from '@/lib/tour-stops';
import { tourTabId, tourTabPanelId } from '@/lib/tour-tabs';
import type { TourDateAdmin } from '@/types/logistics';
import type {
    SupportedCurrency,
    Tour,
    TourCategory,
    TourFormPayload,
    TourFormStep,
    TourFormStepId,
    TourSubmitPayload,
    TourStatus as TourStatusType,
} from '@/types/tour';

const { t } = useTranslations();

const api = useApi();

type Props = {
    tour: Tour;
    categories: TourCategory[];
};

const props = defineProps<Props>();

const { can } = usePermissions();

/**
 * Pestaña «Pasajeros»: solo con `bookings.view`. Regla de oro del menú (F018):
 * si la pestaña aparece, la API que hay detrás responde 200 — y sin ese permiso
 * la planilla devuelve 403.
 */
const canViewPassengers = computed(() => can('bookings.view'));

type TourEditTab = 'content' | 'route' | 'departures' | 'passengers';

const activeTab = ref<TourEditTab>('content');

const initialValues = computed<TourFormPayload>(() => ({
    name: props.tour.name,
    short_description: props.tour.short_description ?? '',
    description: props.tour.description ?? '',
    category_id: props.tour.category_id,
    base_price: props.tour.base_price,
    currency: props.tour.currency as SupportedCurrency,
    duration_hours: props.tour.duration_hours,
    default_guide_id: props.tour.default_guide_id,
    difficulty: props.tour.difficulty,
    default_capacity: props.tour.default_capacity,
    meeting_point: props.tour.meeting_point ?? '',
    meeting_latitude: props.tour.meeting_latitude ?? '',
    meeting_longitude: props.tour.meeting_longitude ?? '',
    includes: props.tour.includes ?? [],
    excludes: props.tour.excludes ?? [],
    requirements: props.tour.requirements ?? [],
    itinerary: (props.tour.itinerary ?? []).map((step) => ({
        step_number: step.step_number,
        title: step.title,
        description: step.description ?? '',
        duration_label: step.duration_label ?? '',
    })),
    stops: tourStopDraftsFrom(props.tour.stops ?? []),
}));

const form = useForm<TourFormPayload>(() => ({ ...initialValues.value }));
const formErrors = computed(
    () => form.errors as Record<string, string | undefined>,
);
const saving = ref(false);
const statusError = ref<string | null>(null);
const changingStatus = ref(false);

const payload = computed<TourFormPayload>(() => form.data());

/**
 * Cuántos campos cambiaron respecto de lo guardado. `form.isDirty` responde
 * sí/no; la savebar del handoff dice «3 cambios sin guardar», que es lo que
 * permite decidir si vale la pena descartar.
 */
const changedFields = computed<number>(() => {
    const current = payload.value as Record<string, unknown>;
    const initial = initialValues.value as Record<string, unknown>;

    return Object.keys(initial).filter(
        (key) => JSON.stringify(current[key]) !== JSON.stringify(initial[key]),
    ).length;
});

function discardChanges(): void {
    form.clearErrors();
    durationConflict.value = null;
    Object.assign(form, { ...initialValues.value });
}

const imagesCount = computed<number>(() => props.tour.images.length);

const { steps, requirements, blockingCount } = useTourCompletion(payload, {
    imagesCount,
    // El reparto bloqueante/recomendado lo decide el servidor (D7).
    serverRequirements: props.tour.publish_checklist,
});

/** El bloque «Ruta y mapa» vive en su propia pestaña; el resto, en «Contenido». */
const CONTENT_SECTIONS: TourFormStepId[] = [
    'general',
    'pricing',
    'detail',
    'gallery',
];

const activeStep = ref<TourFormStepId | null>('general');

function goToStep(step: TourFormStep): void {
    activeStep.value = step.id;
    activeTab.value = step.id === 'route' ? 'route' : 'content';

    void nextTick(() => {
        document
            .getElementById(step.anchor)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

// ---------------------------------------------------------------- Mapa

/**
 * Regla 6: si la recogida del formulario ya no es la guardada, el aviso deja de
 * ser hipotético. Se compara lo que le importa al pasajero —dónde y a qué
 * hora—, no el orden ni el resto de las paradas.
 */
const pickupChanged = computed<boolean>(() => {
    const saved = (props.tour.stops ?? []).find(
        (stop) => stop.kind === 'pickup',
    );
    const draft = payload.value.stops.find((stop) => stop.kind === 'pickup');

    if (saved === undefined || draft === undefined) {
        return saved !== draft;
    }

    return (
        saved.name !== draft.name.trim() ||
        (saved.place ?? '') !== draft.place.trim() ||
        (saved.time ?? '') !== draft.time.trim() ||
        Number(saved.latitude) !== Number(draft.latitude) ||
        Number(saved.longitude) !== Number(draft.longitude)
    );
});

// ------------------------------------------------------------- Salidas

const {
    departures,
    loading: departuresLoading,
    error: departuresError,
    options: departureOptions,
    scheduledCount,
    openCount,
    load: loadDepartures,
    loadOptions: loadDepartureOptions,
} = useTourDepartures(props.tour.id);

const {
    summary: manifestSummary,
    preview: manifestPreview,
    loading: manifestLoading,
    load: loadManifestSummary,
} = useTourManifestSummary(props.tour.id);

const dateDialogOpen = ref(false);
const editingDate = ref<TourDateAdmin | null>(null);

const cancelOpen = ref(false);
const cancelTarget = ref<TourDateAdmin | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);

function openCreateDate(): void {
    editingDate.value = null;
    dateDialogOpen.value = true;
}

function openEditDate(departure: TourDateAdmin): void {
    editingDate.value = departure;
    dateDialogOpen.value = true;
}

function openCancelDate(departure: TourDateAdmin): void {
    cancelTarget.value = departure;
    cancelReason.value = '';
    cancelOpen.value = true;
}

function confirmCancelDate(): void {
    const target = cancelTarget.value;

    if (target === null || cancelling.value) {
        return;
    }

    cancelling.value = true;

    void api.patch(
        CancelTourDateController(target.id).url,
        { reason: cancelReason.value.trim() || null },
        {
            onSuccess: () => {
                toast.success(t('Salida cancelada.'));
                cancelOpen.value = false;
                void loadDepartures();
            },
            onError: (errors) => {
                toast.error(
                    errors._global ?? t('No se pudo cancelar la salida.'),
                );
            },
            onFinish: () => {
                cancelling.value = false;
            },
        },
    );
}

function removeDate(departure: TourDateAdmin): void {
    if (
        !confirm(
            t(
                '¿Eliminar esta salida? Solo se puede si no tiene reservas asociadas.',
            ),
        )
    ) {
        return;
    }

    void api.delete(destroyDate(departure.id).url, {
        onSuccess: () => {
            toast.success(t('Salida eliminada.'));
            void loadDepartures();
        },
        onError: (errors) => {
            toast.error(errors._global ?? t('No se pudo eliminar la salida.'));
        },
    });
}

/**
 * WHY: la planilla trae su propio selector de salida, y `PassengerManifest` no
 * recibe filtro inicial: desde la fila se abre la pestaña y allí se elige. No se
 * inventa un prop que la Fase 4 no expone.
 */
function openPassengersOf(): void {
    activeTab.value = 'passengers';
}

/** La ficha pública del tour, para comprobar cómo se ve lo que se acaba de guardar. */
const publicUrl = computed(() => publicTour({ slug: props.tour.slug }).url);

/**
 * La lista completa vive en el detalle del tour. Se abre directamente en su
 * pestaña de pasajeros para que el salto no cueste dos clics.
 */
function openFullManifest(): void {
    router.visit(showPage({ tour: props.tour.id }).url + '?tab=passengers');
}

onMounted(() => {
    void loadDepartures();
    void loadDepartureOptions();

    if (canViewPassengers.value) {
        void loadManifestSummary();
    }
});

// -------------------------------------------------------------- Guardar

const durationConflict = ref<string | null>(null);

function normalizePayload(data: TourFormPayload): TourSubmitPayload {
    return {
        ...data,
        meeting_latitude:
            data.meeting_latitude === '' ? null : data.meeting_latitude,
        meeting_longitude:
            data.meeting_longitude === '' ? null : data.meeting_longitude,
        meeting_point: data.meeting_point === '' ? null : data.meeting_point,
        short_description:
            data.short_description === '' ? null : data.short_description,
        stops: tourStopsPayload(data.stops),
    };
}

function submit(): void {
    form.clearErrors();
    durationConflict.value = null;
    saving.value = true;

    void api.put(
        updateTour({ tour: props.tour.id }).url,
        normalizePayload(form.data()),
        {
            onSuccess: () => {
                toast.success(t('Cambios guardados.'));
                router.reload({ only: ['tour'] });
                void loadDepartures();
            },
            onError: (errors) => {
                form.setError(errors);

                // WHY (D9): alargar el tour puede cruzar salidas ya programadas.
                // El 422 nombra cuáles y ese texto no puede quedar escondido en
                // un campo de otra pestaña.
                if (errors.duration_hours) {
                    durationConflict.value = errors.duration_hours;
                    activeTab.value = 'content';
                }

                toast.error(t('Revisa los campos marcados.'));
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

// --------------------------------------------------------------- Estado

const allowedNextStatuses = computed<TourStatusType[]>(() => {
    switch (props.tour.status) {
        case 'draft':
            return ['active', 'archived'];
        case 'active':
            return ['paused', 'archived'];
        case 'paused':
            return ['active', 'archived'];
        case 'archived':
            return ['draft'];
        default:
            return [];
    }
});

function statusLabel(status: TourStatusType): string {
    switch (status) {
        case 'active':
            return t('Publicar');
        case 'paused':
            return t('Pausar');
        case 'archived':
            return t('Archivar');
        case 'draft':
            return t('Volver a borrador');
        default:
            return status;
    }
}

const STATUS_ICONS: Record<string, typeof CircleCheck> = {
    active: CircleCheck,
    paused: PauseCircle,
    archived: Archive,
    draft: FileText,
};

function statusIcon(status: TourStatusType) {
    return STATUS_ICONS[status] ?? CircleCheck;
}

const STATUS_ERROR_MESSAGES: Record<string, string> = {
    TOUR_NEEDS_IMAGE_TO_ACTIVATE: t(
        'El tour necesita al menos una imagen antes de activarse.',
    ),
    TOUR_NEEDS_GUIDE_TO_ACTIVATE: t(
        'El tour necesita un guía por defecto antes de activarse.',
    ),
    INVALID_STATUS_TRANSITION: t(
        'Ese cambio de estado no es válido desde el estado actual del tour.',
    ),
    TOUR_HAS_ACTIVE_BOOKINGS: t(
        'El tour tiene reservas activas: archívalo en lugar de cambiarlo de estado.',
    ),
    FEATURE_REQUIRES_ENTERPRISE: t(
        'Esta acción solo está disponible en el plan Enterprise.',
    ),
};

function statusErrorMessage(errors: ApiErrors): string {
    const mapped = errors.error_code
        ? STATUS_ERROR_MESSAGES[errors.error_code]
        : undefined;

    if (mapped) {
        return mapped;
    }

    if (!errors.error_code && errors._global) {
        return errors._global;
    }

    return t(
        'No se pudo cambiar el estado del tour. Revisa que tenga al menos una imagen y que el cambio sea válido desde su estado actual.',
    );
}

function transitionTo(next: TourStatusType): void {
    statusError.value = null;
    changingStatus.value = true;

    void api.patch(
        changeStatus({ tour: props.tour.id }).url,
        { status: next },
        {
            onSuccess: () => {
                toast.success(t('Estado actualizado.'));
                router.reload({ only: ['tour'] });
            },
            onError: (errors) => {
                statusError.value = statusErrorMessage(errors);
            },
            onFinish: () => {
                changingStatus.value = false;
            },
        },
    );
}

function deleteTour(): void {
    if (
        !confirm(
            t(
                '¿Eliminar este tour? Esta acción se puede revertir desde tu base de datos.',
            ),
        )
    ) {
        return;
    }

    void api.delete(destroyTour({ tour: props.tour.id }).url, {
        onSuccess: () => {
            toast.success(t('Tour eliminado.'));
            router.visit(indexPage().url);
        },
        onError: (errors) => {
            if (errors.error_code === 'TOUR_HAS_ACTIVE_BOOKINGS') {
                toast.error(
                    t(
                        'No se puede eliminar: hay reservas activas. Archivalo en su lugar.',
                    ),
                );

                return;
            }

            toast.error(t('No se pudo eliminar el tour.'));
        },
    });
}

// -------------------------------------------------------------- Pestañas

const tabs = computed<TourTabItem[]>(() => {
    const items: TourTabItem[] = [
        { id: 'content', label: t('Contenido') },
        { id: 'route', label: t('Ruta y mapa') },
        {
            id: 'departures',
            label: t('Salidas'),
            count: departuresLoading.value ? null : scheduledCount.value,
        },
    ];

    if (canViewPassengers.value) {
        items.push({
            id: 'passengers',
            label: t('Pasajeros'),
            count: manifestSummary.value?.total_passengers ?? null,
        });
    }

    return items;
});

function selectTab(id: string): void {
    activeTab.value = id as TourEditTab;
}

const lastEdited = computed<string | null>(() =>
    props.tour.updated_at ? formatRelativeDate(props.tour.updated_at) : null,
);
</script>

<template>
    <div class="px-4 py-6 md:px-8">
        <Head :title="$t('Editar: :name', { name: props.tour.name })" />

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <Link
                    :href="indexPage().url"
                    class="inline-flex items-center gap-1.5 text-[13px] text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-3.5" />
                    {{ $t('Volver a tours') }}
                </Link>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <TourStatusBadge :status="props.tour.status" />
                    <span class="font-mono text-xs text-muted-foreground"
                        >/{{ props.tour.slug }}</span
                    >
                </div>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight">
                    {{ props.tour.name }}
                </h1>
                <p
                    v-if="lastEdited"
                    class="mt-1 text-[13px] text-muted-foreground"
                >
                    {{ $t('Última edición :when', { when: lastEdited }) }}
                </p>
            </div>

            <!--
              WHY: «Ver detalle», los cambios de estado y «Eliminar» eran hasta
              cuatro botones en fila que empujaban el título y ponían una acción
              destructiva al mismo nivel visual que el resto. El sistema de
              diseño pide un único menú ⋯; cada opción conserva su icono y la
              destructiva va en `danger`, separada.
            -->
            <div class="flex flex-wrap items-center gap-2">
                <Loader2
                    v-if="changingStatus"
                    class="size-4 animate-spin text-muted-foreground"
                />
                <ActionMenu :label="$t('Acciones del tour')">
                    <DropdownMenuItem as-child>
                        <Link :href="showPage({ tour: props.tour.id }).url">
                            <ExternalLink class="size-4" />
                            {{ $t('Ver detalle') }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="next in allowedNextStatuses"
                        :key="next"
                        :disabled="changingStatus"
                        @select="transitionTo(next)"
                    >
                        <component :is="statusIcon(next)" class="size-4" />
                        {{ statusLabel(next) }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        :disabled="changingStatus"
                        @select="deleteTour"
                    >
                        <Trash2 class="size-4" />
                        {{ $t('Eliminar') }}
                    </DropdownMenuItem>
                </ActionMenu>
            </div>
        </div>

        <Alert v-if="statusError" variant="destructive" class="mt-4">
            <AlertTitle>{{ $t('No se pudo cambiar el estado') }}</AlertTitle>
            <AlertDescription>{{ statusError }}</AlertDescription>
        </Alert>

        <Alert v-if="durationConflict" variant="destructive" class="mt-4">
            <AlertTitle>{{
                $t('La nueva duración cruza salidas ya programadas')
            }}</AlertTitle>
            <AlertDescription>{{ durationConflict }}</AlertDescription>
        </Alert>

        <TourTabs
            class="mt-5"
            :tabs="tabs"
            :model-value="activeTab"
            :label="$t('Secciones del tour')"
            @update:model-value="selectTab"
        />

        <!--
          La columna de contexto acompaña a TODAS las pestañas. Antes solo
          existía en «Contenido» y desaparecía al pasar a «Salidas», que es
          donde más falta hace saber si el tour está publicado antes de abrir
          una salida a la venta.
        -->
        <div
            class="mt-5 grid grid-cols-1 items-start gap-6 min-[1180px]:grid-cols-[minmax(0,1fr)_320px]"
        >
            <div class="min-w-0">
                <form @submit.prevent="submit">
                    <!--
                  `v-show`, no `v-if`: los dos bloques del formulario son una
                  sola instancia de estado repartida en dos pestañas, y
                  desmontarlos perdería el mapa y el foco al cambiar de pestaña.
                  Los `id` de las secciones no se repiten porque `sections`
                  reparte bloques distintos entre las dos instancias.
                -->
                    <div
                        v-show="activeTab === 'content'"
                        :id="tourTabPanelId('content')"
                        role="tabpanel"
                        :aria-labelledby="tourTabId('content')"
                        tabindex="0"
                    >
                        <TourForm
                            :model-value="payload"
                            :errors="formErrors"
                            :categories="props.categories"
                            :sections="CONTENT_SECTIONS"
                            @update:model-value="
                                (value) => applyFormValue(form, value)
                            "
                        >
                            <template #gallery>
                                <Card>
                                    <CardHeader>
                                        <div
                                            class="flex items-start justify-between gap-4"
                                        >
                                            <div>
                                                <CardTitle>{{
                                                    $t('Galería')
                                                }}</CardTitle>
                                                <CardDescription>{{
                                                    $t(
                                                        'JPG, PNG o WebP. La portada es la primera imagen.',
                                                    )
                                                }}</CardDescription>
                                            </div>
                                            <MonoLabel class="shrink-0 pt-1">{{
                                                $t('Paso :number', {
                                                    number: 5,
                                                })
                                            }}</MonoLabel>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <TourImageUploader
                                            :tour-id="props.tour.id"
                                            :images="props.tour.images"
                                        />
                                    </CardContent>
                                </Card>
                            </template>
                        </TourForm>
                    </div>

                    <div
                        v-show="activeTab === 'route'"
                        :id="tourTabPanelId('route')"
                        role="tabpanel"
                        :aria-labelledby="tourTabId('route')"
                        tabindex="0"
                        class="space-y-4"
                    >
                        <PickupChangeNotice
                            :impact="props.tour.pickup_change_impact"
                            :pending="pickupChanged"
                        />

                        <TourForm
                            :model-value="payload"
                            :errors="formErrors"
                            :categories="props.categories"
                            :sections="['route']"
                            @update:model-value="
                                (value) => applyFormValue(form, value)
                            "
                        />

                        <!--
                          WHY: acá había un segundo mapa —«Vista previa de la
                          ruta»— con los mismos pines que el del editor, uno
                          debajo del otro. El editor ya es el mapa, y editable;
                          repetirlo solo alargaba la pestaña.
                        -->
                    </div>

                    <StickySaveBar v-show="activeTab !== 'passengers'">
                        <template #note>
                            <span v-if="changedFields > 0">
                                {{
                                    $tc(
                                        ':count cambio sin guardar|:count cambios sin guardar',
                                        changedFields,
                                        { count: changedFields },
                                    )
                                }}
                            </span>
                            <span v-else-if="blockingCount > 0">
                                {{
                                    $tc(
                                        'Falta :count condición para publicar|Faltan :count condiciones para publicar',
                                        blockingCount,
                                        { count: blockingCount },
                                    )
                                }}
                            </span>
                            <span v-else>{{ $t('Todo guardado.') }}</span>
                        </template>
                        <template #actions>
                            <Button
                                type="button"
                                variant="ghost"
                                :disabled="changedFields === 0 || saving"
                                @click="discardChanges"
                            >
                                {{ $t('Descartar') }}
                            </Button>
                            <Button type="submit" :disabled="saving">
                                {{
                                    saving
                                        ? $t('Guardando…')
                                        : $t('Guardar cambios')
                                }}
                            </Button>
                        </template>
                    </StickySaveBar>
                </form>

                <section
                    v-show="activeTab === 'departures'"
                    :id="tourTabPanelId('departures')"
                    role="tabpanel"
                    :aria-labelledby="tourTabId('departures')"
                    tabindex="0"
                >
                    <TourDeparturesTable
                        :departures="departures"
                        :currency="props.tour.currency"
                        :duration-hours="props.tour.duration_hours"
                        :loading="departuresLoading"
                        :error="departuresError"
                        :fallback-guides="departureOptions.guides"
                        :can-view-passengers="canViewPassengers"
                        @create="openCreateDate"
                        @edit="openEditDate"
                        @cancel="openCancelDate"
                        @remove="removeDate"
                        @passengers="openPassengersOf"
                        @assigned="loadDepartures"
                        @retry="loadDepartures"
                    />
                </section>

                <!--
              La edición muestra un AVANCE de la planilla, no la planilla: la
              lista completa —con filtros, exportación e impresión— vive en el
              detalle del tour, que es la pantalla de operación.
            -->
                <section
                    v-show="activeTab === 'passengers'"
                    :id="tourTabPanelId('passengers')"
                    role="tabpanel"
                    :aria-labelledby="tourTabId('passengers')"
                    tabindex="0"
                >
                    <TourPassengerPreview
                        :passengers="manifestPreview"
                        :total="manifestSummary?.total_passengers ?? 0"
                        :loading="manifestLoading"
                        @open="openFullManifest"
                    />
                </section>
            </div>

            <!--
              WHY fuera de los `tabpanel`: la rejilla la pone al lado del panel
              activo, así que es un landmark complementario con nombre propio y
              no un segundo `tabpanel` de la misma pestaña, que no sería válido.
            -->
            <aside
                :aria-label="$t('Contexto del tour')"
                class="flex flex-col gap-4 min-[1180px]:sticky min-[1180px]:top-20"
            >
                <TourStatusRailCard
                    :status="props.tour.status"
                    :public-url="publicUrl"
                    :images="props.tour.images"
                />
                <template v-if="activeTab === 'content'">
                    <TourProgressRail
                        :steps="steps"
                        :active-id="activeStep"
                        @select="goToStep"
                    />
                    <TourPublishChecklist :requirements="requirements" />
                </template>
                <TourImpactCard
                    :summary="manifestSummary"
                    :open-departures="openCount"
                    :loading="manifestLoading || departuresLoading"
                    :can-view-passengers="canViewPassengers"
                    @view-passengers="activeTab = 'passengers'"
                />
            </aside>
        </div>

        <TourDateFormDialog
            v-model:open="dateDialogOpen"
            :tour-id="props.tour.id"
            :editing="editingDate"
            :duration-hours="props.tour.duration_hours"
            :default-guide-id="props.tour.default_guide_id"
            :guides="departureOptions.guides"
            :routes="departureOptions.routes"
            :providers="departureOptions.providers"
            :hotels="departureOptions.hotels"
            @saved="loadDepartures"
        />

        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Cancelar salida') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'La salida dejará de mostrarse en el catálogo público. Las reservas existentes no se modifican.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="cancel-reason">{{
                        $t('Motivo (opcional)')
                    }}</Label>
                    <Textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        rows="3"
                        :placeholder="$t('Ej: clima adverso')"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="cancelling"
                        @click="cancelOpen = false"
                    >
                        {{ $t('Volver') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="cancelling"
                        @click="confirmCancelDate"
                    >
                        <Loader2
                            v-if="cancelling"
                            class="size-4 animate-spin"
                        />
                        {{ $t('Cancelar salida') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
