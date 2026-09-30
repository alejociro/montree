<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ChevronLeft,
    Plus,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    store as storeDate,
    update as updateDate,
} from '@/actions/App/Http/Controllers/Admin/TourDatePagesController';
import ChipsInput from '@/components/molecules/ChipsInput.vue';
import GuideSelect from '@/components/molecules/GuideSelect.vue';
import StickySaveBar from '@/components/molecules/StickySaveBar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useModules } from '@/composables/useModules';
import { useTenantCurrency } from '@/composables/useTenant';
import { useTranslations } from '@/composables/useTranslations';
import {
    formatCurrency,
    formatNumericDateTime,
    formatTourDate,
} from '@/lib/format';
import type { DepartureRange } from '@/types/guide-availability';
import type {
    DepartureOptions,
    DepartureTourOption,
    TourDateAdmin,
    TourDateItineraryStep,
} from '@/types/logistics';

const { t } = useTranslations();

const tenantCurrency = useTenantCurrency();
const { isModuleEnabled } = useModules();
const logisticsEnabled = computed(() => isModuleEnabled('logistics'));

type Props = {
    mode: 'create' | 'edit';
    tourDate: TourDateAdmin | null;
    tours: DepartureTourOption[];
    preselectedTourId: number | null;
    departureOptions: DepartureOptions;
    returnUrl: string;
};

const props = defineProps<Props>();

const isEditing = computed(() => props.mode === 'edit');

// ------------------------------------------------------------- Producto

const selectedTourId = ref<number | null>(props.preselectedTourId);

/** Solo la página de creación desde el tablero pide elegir producto. */
const tourIsPreselected = computed(() => props.preselectedTourId !== null);

const selectedTour = computed<DepartureTourOption | null>(
    () => props.tours.find((tour) => tour.id === selectedTourId.value) ?? null,
);

// -------------------------------------------------------------- Fechas

const MS_PER_HOUR = 3_600_000;

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toDateOnly(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function toDateTimeLocal(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toIso(local: string): string | null {
    if (local === '') {
        return null;
    }

    const date = new Date(local);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

// --------------------------------------------------------------- Form

const form = useForm({
    starts_at: '',
    capacity: 10,
    price_override: '',
    min_payment_pct: '' as string | number,
    notes: '',
    guide_id: null as number | null,
    route_id: null as number | null,
    provider_id: null as number | null,
    hotel_ids: [] as number[],
    booking_closes_at: '',
});

type ItineraryStepDraft = {
    step_number: number;
    title: string;
    description: string;
    duration_label: string;
};

function toDraftSteps(steps: TourDateItineraryStep[]): ItineraryStepDraft[] {
    return steps.map((step) => ({
        step_number: step.step_number,
        title: step.title,
        description: step.description ?? '',
        duration_label: step.duration_label ?? '',
    }));
}

type ContentDraft = {
    itinerary: ItineraryStepDraft[];
    includes: string[];
    excludes: string[];
    requirements: string[];
    meetingPoint: string;
};

const customize = ref({
    itinerary: false,
    includes: false,
    excludes: false,
    requirements: false,
    meetingPoint: false,
});

const draft = ref<ContentDraft>({
    itinerary: [],
    includes: [],
    excludes: [],
    requirements: [],
    meetingPoint: '',
});

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}

const localErrors = ref<Partial<Record<string, string>>>({});

const errors = computed<Record<string, string | undefined>>(() => ({
    ...(form.errors as Record<string, string | undefined>),
    ...localErrors.value,
}));

/**
 * Precarga el formulario con lo que hereda el producto elegido. Solo corre en
 * creación: editar no vuelve a proponer nada, la salida ya tiene sus valores.
 */
function applyTourDefaults(tour: DepartureTourOption): void {
    const defaults = tour.departure_defaults;

    form.capacity = defaults.capacity;
    form.price_override = '';
    form.min_payment_pct = '';
    form.guide_id = defaults.guide_id;
    form.route_id = tour.routes.find((route) => route.is_default)?.id ?? null;

    customize.value = {
        itinerary: false,
        includes: false,
        excludes: false,
        requirements: false,
        meetingPoint: false,
    };
    draft.value = {
        itinerary: toDraftSteps(defaults.itinerary),
        includes: clone(defaults.includes),
        excludes: clone(defaults.excludes),
        requirements: clone(defaults.requirements),
        meetingPoint: defaults.meeting_point ?? '',
    };
}

function applyFromEditing(date: TourDateAdmin): void {
    form.starts_at = toDateTimeLocal(date.starts_at);
    form.capacity = date.capacity;
    form.price_override = date.price_override ?? '';
    form.min_payment_pct =
        date.min_payment_pct === null ? '' : String(date.min_payment_pct);
    form.notes = date.notes ?? '';
    form.guide_id = date.guide?.id ?? null;
    form.route_id = date.route?.id ?? null;
    form.provider_id = date.provider?.id ?? null;
    form.hotel_ids = date.hotels.map((hotel) => hotel.id);
    form.booking_closes_at = toDateTimeLocal(date.booking_closes_at);

    customize.value = {
        itinerary: date.itinerary !== null,
        includes: date.includes !== null,
        excludes: date.excludes !== null,
        requirements: date.requirements !== null,
        meetingPoint: date.meeting_point !== null,
    };

    const tour = selectedTour.value;
    const defaults = tour?.departure_defaults ?? {
        itinerary: [],
        includes: [],
        excludes: [],
        requirements: [],
        meeting_point: null,
    };

    draft.value = {
        itinerary: toDraftSteps(date.itinerary ?? defaults.itinerary),
        includes: clone(date.includes ?? defaults.includes),
        excludes: clone(date.excludes ?? defaults.excludes),
        requirements: clone(date.requirements ?? defaults.requirements),
        meetingPoint: date.meeting_point ?? defaults.meeting_point ?? '',
    };
}

if (isEditing.value && props.tourDate !== null) {
    applyFromEditing(props.tourDate);
} else if (selectedTour.value !== null) {
    applyTourDefaults(selectedTour.value);
}

/** Solo en creación: elegir producto (re)precarga sus valores por defecto. */
watch(selectedTourId, (id, previousId) => {
    if (isEditing.value || id === previousId) {
        return;
    }

    const tour = props.tours.find((candidate) => candidate.id === id);

    if (tour) {
        applyTourDefaults(tour);
    }
});

// WHY (D9): el fin no se escribe, se deriva de la duración del producto.
const durationHours = computed<number | null>(
    () => selectedTour.value?.duration_hours ?? null,
);

const derivedEnd = computed<Date | null>(() => {
    if (form.starts_at === '' || durationHours.value === null) {
        return null;
    }

    const start = new Date(form.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    return new Date(start.getTime() + durationHours.value * MS_PER_HOUR);
});

const derivedEndLabel = computed(() =>
    derivedEnd.value === null
        ? t('Se calcula con la duración del tour.')
        : // WHY: mismo formato numérico (dd/mm/aaaa hh:mm) que el campo de
          // inicio, para que inicio y fin se lean igual.
          formatNumericDateTime(derivedEnd.value.toISOString()),
);

// T12: cuando la salida no tiene su propio cierre, se muestra lo heredado
// —la regla de la agencia (horas antes del inicio) o, sin regla, que se
// puede reservar hasta la hora de salida—.
const agencyAdvanceHours = computed<number | null>(
    () => selectedTour.value?.departure_defaults.booking_advance_hours ?? null,
);

const inheritedBookingClosesLabel = computed(() => {
    if (form.starts_at === '') {
        return null;
    }

    const start = new Date(form.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    if (agencyAdvanceHours.value === null) {
        return t('Sin regla de la agencia: hasta la hora de salida.');
    }

    const closesAt = new Date(
        start.getTime() - agencyAdvanceHours.value * MS_PER_HOUR,
    );

    return t('Por defecto de la agencia: :hours h antes → :date', {
        hours: agencyAdvanceHours.value,
        date: formatNumericDateTime(closesAt.toISOString()),
    });
});

const guideRange = computed<DepartureRange | null>(() => {
    if (form.starts_at === '') {
        return null;
    }

    const start = new Date(form.starts_at);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    const end = derivedEnd.value ?? start;

    return { from: toDateOnly(start), to: toDateOnly(end) };
});

/**
 * Una salida con reservas activas no puede mover su inicio (regla del
 * servidor, `UpdateTourDateAction::hasActiveBookings`). `booked_count > 0` es
 * la señal que ya viaja en el recurso — se bloquea acá para no perder el
 * formulario entero al 422, y el servidor sigue siendo quien decide de verdad.
 */
const startsAtLocked = computed(
    () => isEditing.value && (props.tourDate?.booked_count ?? 0) > 0,
);

const basePriceLabel = computed(() =>
    selectedTour.value
        ? formatCurrency(
              selectedTour.value.departure_defaults.base_price,
              tenantCurrency.value,
          )
        : null,
);

const agencyMinPaymentPct = computed(
    () => selectedTour.value?.departure_defaults.min_payment_pct ?? null,
);

function minPaymentPctPayload(): number | null {
    const trimmed = String(form.min_payment_pct).trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = Number(trimmed);

    return Number.isNaN(parsed) ? null : Math.trunc(parsed);
}

function parseSelectId(value: string): number | null {
    return value === '' ? null : Number(value);
}

function toggleHotel(hotelId: number): void {
    const index = form.hotel_ids.indexOf(hotelId);

    if (index === -1) {
        form.hotel_ids.push(hotelId);

        return;
    }

    form.hotel_ids.splice(index, 1);
}

function addItineraryStep(): void {
    draft.value.itinerary.push({
        step_number: draft.value.itinerary.length + 1,
        title: '',
        description: '',
        duration_label: '',
    });
}

function removeItineraryStep(index: number): void {
    draft.value.itinerary.splice(index, 1);
    draft.value.itinerary.forEach((step, position) => {
        step.step_number = position + 1;
    });
}

// ---------------------------------------------------------------- Pasos

type StepId = 'date' | 'pricing' | 'guide' | 'content' | 'review';

type Step = {
    id: StepId;
    label: string;
    hint: string;
};

const steps = computed<Step[]>(() => [
    {
        id: 'date',
        label: t('Producto y fecha'),
        hint: t('Qué se programa y cuándo'),
    },
    {
        id: 'pricing',
        label: t('Cupos y precio'),
        hint: t('Capacidad, precio y abono'),
    },
    {
        id: 'guide',
        label: t('Guía y ruta'),
        hint: t('Quién la lleva y por dónde'),
    },
    {
        id: 'content',
        label: t('Contenido de la salida'),
        hint: t('Itinerario, incluye y punto de encuentro'),
    },
    {
        id: 'review',
        label: t('Revisar y guardar'),
        hint: t('Confirma antes de guardar'),
    },
]);

const activeStepIndex = ref(0);
const activeStep = computed(() => steps.value[activeStepIndex.value]);

function goToStep(index: number): void {
    activeStepIndex.value = Math.max(
        0,
        Math.min(index, steps.value.length - 1),
    );
}

function goToStepId(id: StepId): void {
    const index = steps.value.findIndex((step) => step.id === id);

    if (index !== -1) {
        goToStep(index);
    }
}

const STEP_FIELDS: Record<StepId, string[]> = {
    date: ['tour', 'starts_at', 'ends_at', 'booking_closes_at'],
    pricing: ['capacity', 'price_override', 'min_payment_pct'],
    guide: ['guide_id', 'route_id', 'provider_id', 'hotel_ids'],
    content: [
        'itinerary',
        'includes',
        'excludes',
        'requirements',
        'meeting_point',
    ],
    review: ['tour_date'],
};

function stepHasError(step: Step): boolean {
    return Object.keys(errors.value).some(
        (field) =>
            errors.value[field] !== undefined &&
            STEP_FIELDS[step.id].some(
                (prefix) => field === prefix || field.startsWith(`${prefix}.`),
            ),
    );
}

/** Campos obligatorios: producto (si toca elegirlo), inicio y capacidad. */
const requiredChecks = computed<{ done: boolean }[]>(() => {
    const checks: { done: boolean }[] = [];

    if (!tourIsPreselected.value) {
        checks.push({ done: selectedTourId.value !== null });
    }

    checks.push({ done: form.starts_at !== '' });
    checks.push({ done: Number(form.capacity) >= 1 });

    return checks;
});

const requiredDoneCount = computed(
    () => requiredChecks.value.filter((check) => check.done).length,
);
const requiredTotalCount = computed(() => requiredChecks.value.length);
const canSave = computed(
    () => requiredDoneCount.value === requiredTotalCount.value,
);

function stepIsDone(step: Step): boolean {
    // Sin producto elegido todavía no hay nada que llenar: el resto de pasos
    // se queda pendiente aunque sus campos no tengan nada obligatorio.
    if (selectedTour.value === null) {
        return false;
    }

    switch (step.id) {
        case 'date':
            return form.starts_at !== '';
        case 'pricing':
            return Number(form.capacity) >= 1;
        case 'guide':
        case 'content':
            return true;
        case 'review':
            return canSave.value;
        default:
            return false;
    }
}

// -------------------------------------------------------- Guardar / salir

const processing = computed(() => form.processing);

function validateLocally(): boolean {
    localErrors.value = {};

    if (!tourIsPreselected.value && selectedTourId.value === null) {
        localErrors.value.tour = t('Elige un producto.');
    }

    if (form.starts_at === '') {
        localErrors.value.starts_at = t('La fecha de inicio es obligatoria.');
    }

    if (Number(form.capacity) < 1) {
        localErrors.value.capacity = t('La capacidad debe ser al menos 1.');
    }

    const minPaymentPct = minPaymentPctPayload();

    if (minPaymentPct !== null && (minPaymentPct < 1 || minPaymentPct > 100)) {
        localErrors.value.min_payment_pct = t(
            'El mínimo de abono debe estar entre 1 y 100.',
        );
    }

    return Object.keys(localErrors.value).length === 0;
}

const dirty = ref(false);

watch(
    () => JSON.stringify([form.data(), customize.value, draft.value]),
    () => {
        dirty.value = true;
    },
);

let allowNavigation = false;

function confirmLeave(): boolean {
    if (!dirty.value || allowNavigation) {
        return true;
    }

    return window.confirm(
        t('Tienes cambios sin guardar. ¿Quieres salir de todas formas?'),
    );
}

const removeBeforeGuard = router.on('before', (event) => {
    if (!confirmLeave()) {
        event.preventDefault();
    }
});

function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (dirty.value && !allowNavigation) {
        event.preventDefault();
    }
}

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeBeforeGuard();
});

function cancel(): void {
    if (!confirmLeave()) {
        return;
    }

    allowNavigation = true;
    router.visit(props.returnUrl);
}

function submit(): void {
    if (form.processing || !validateLocally()) {
        if (Object.keys(localErrors.value).length > 0) {
            const firstField = Object.keys(localErrors.value)[0];
            const step =
                firstField === 'tour'
                    ? 'date'
                    : firstField === 'starts_at'
                      ? 'date'
                      : firstField === 'capacity'
                        ? 'pricing'
                        : firstField === 'min_payment_pct'
                          ? 'pricing'
                          : 'date';
            goToStepId(step);
        }

        return;
    }

    const tourId = selectedTourId.value;

    if (tourId === null) {
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            allowNavigation = true;
            // El propio `store`/`update` ya redirige a `returnUrl`
            // (`redirectAfterSave` en el controlador) — Inertia sigue esa
            // redirección como parte de esta misma visita, así que no hace
            // falta (ni conviene: competía con ella) otro `router.visit`.
            toast.success(
                isEditing.value
                    ? t('Salida actualizada.')
                    : t('Salida creada.'),
            );
        },
        onError: (serverErrors: Record<string, string>) => {
            // El guardado en sí mismo también es una visita de Inertia: sin
            // esto, el aviso de «cambios sin guardar» se disparaba sobre su
            // propio POST/PUT y, al cancelarlo el usuario, el guardado nunca
            // salía. Un error deja el formulario dirty otra vez.
            allowNavigation = false;
            toast.error(t('Revisa los campos marcados.'));

            const firstField = Object.keys(serverErrors)[0];
            const step = steps.value.find((candidate) =>
                STEP_FIELDS[candidate.id].some(
                    (prefix) =>
                        firstField === prefix ||
                        firstField?.startsWith(`${prefix}.`),
                ),
            );

            if (step) {
                goToStepId(step.id);
            }
        },
    };

    const submitted = form.transform((data) => ({
        ...data,
        return: props.returnUrl,
        starts_at: toIso(String(data.starts_at)),
        price_override:
            String(data.price_override).trim() === ''
                ? null
                : String(data.price_override).trim(),
        min_payment_pct: minPaymentPctPayload(),
        notes:
            String(data.notes).trim() === '' ? null : String(data.notes).trim(),
        booking_closes_at: toIso(String(data.booking_closes_at)),
        itinerary: customize.value.itinerary
            ? draft.value.itinerary.map((step) => ({
                  step_number: step.step_number,
                  title: step.title,
                  description:
                      step.description.trim() === '' ? null : step.description,
                  duration_label:
                      step.duration_label.trim() === ''
                          ? null
                          : step.duration_label,
              }))
            : null,
        includes: customize.value.includes ? draft.value.includes : null,
        excludes: customize.value.excludes ? draft.value.excludes : null,
        requirements: customize.value.requirements
            ? draft.value.requirements
            : null,
        meeting_point:
            customize.value.meetingPoint &&
            draft.value.meetingPoint.trim() !== ''
                ? draft.value.meetingPoint.trim()
                : null,
    }));

    // El guardado es en sí mismo una visita de Inertia: sin marcarla, el
    // guard de «antes de salir» se disparaba sobre su propio POST/PUT.
    allowNavigation = true;

    if (isEditing.value && props.tourDate !== null) {
        submitted.put(updateDate(props.tourDate.id).url, options);

        return;
    }

    submitted.post(storeDate(tourId).url, options);
}

const pageTitle = computed(() =>
    isEditing.value ? t('Editar salida') : t('Nueva salida'),
);

const requiredNote = computed(() =>
    t(':done de :total campos obligatorios', {
        done: requiredDoneCount.value,
        total: requiredTotalCount.value,
    }),
);
</script>

<template>
    <div>
        <Head :title="pageTitle" />

        <div class="px-4 py-6 md:px-8">
            <Button
                variant="ghost"
                size="sm"
                class="mb-3 -ml-2"
                @click="cancel"
            >
                <ArrowLeft class="size-4" />
                {{ $t('Volver') }}
            </Button>

            <header class="mb-5 space-y-0.5">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ pageTitle }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        selectedTour
                            ? selectedTour.name
                            : $t(
                                  'Elige el producto: la salida hereda su capacidad, su precio y su ruta.',
                              )
                    }}
                </p>
            </header>

            <!-- Progreso móvil -->
            <div class="mb-4 md:hidden">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <select
                        :value="activeStep.id"
                        class="h-9 flex-1 rounded-md border border-input bg-card px-2.5 text-sm font-medium"
                        :aria-label="$t('Ir a paso')"
                        @change="
                            goToStepId(
                                ($event.target as HTMLSelectElement)
                                    .value as StepId,
                            )
                        "
                    >
                        <option
                            v-for="(step, index) in steps"
                            :key="step.id"
                            :value="step.id"
                        >
                            {{ index + 1 }}. {{ step.label }}
                        </option>
                    </select>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{
                            $t(':current / :total', {
                                current: activeStepIndex + 1,
                                total: steps.length,
                            })
                        }}
                    </span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{
                            width: `${((activeStepIndex + 1) / steps.length) * 100}%`,
                        }"
                    />
                </div>
            </div>

            <div
                class="grid gap-6 md:grid-cols-[220px_minmax(0,1fr)] md:items-start"
            >
                <!-- Navegación de pasos (escritorio) -->
                <nav
                    :aria-label="$t('Pasos del formulario')"
                    class="hidden md:block"
                >
                    <Card>
                        <CardContent class="space-y-3 p-3">
                            <ol class="flex flex-col gap-0.5">
                                <li
                                    v-for="(step, index) in steps"
                                    :key="step.id"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-primary-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :class="
                                            activeStepIndex === index
                                                ? 'bg-secondary-soft text-secondary-soft-foreground'
                                                : ''
                                        "
                                        :aria-current="
                                            activeStepIndex === index
                                                ? 'step'
                                                : undefined
                                        "
                                        @click="goToStep(index)"
                                    >
                                        <span
                                            class="mt-0.5 grid size-5.5 shrink-0 place-items-center rounded-full border-[1.5px] text-[11px] font-bold"
                                            :class="[
                                                stepHasError(step)
                                                    ? 'border-destructive text-destructive'
                                                    : stepIsDone(step)
                                                      ? 'border-primary bg-primary text-primary-foreground'
                                                      : 'border-input text-muted-foreground',
                                            ]"
                                        >
                                            <Check
                                                v-if="
                                                    stepIsDone(step) &&
                                                    !stepHasError(step)
                                                "
                                                class="size-3"
                                            />
                                            <template v-else>{{
                                                index + 1
                                            }}</template>
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block text-[13px] font-medium"
                                                >{{ step.label }}</span
                                            >
                                            <span
                                                class="block truncate text-[11.5px] text-muted-foreground"
                                                >{{ step.hint }}</span
                                            >
                                        </span>
                                    </button>
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </nav>

                <!-- Contenido del paso -->
                <form class="space-y-4" @submit.prevent="submit">
                    <p
                        v-if="errors.tour_date"
                        class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ errors.tour_date }}
                    </p>

                    <!-- Paso 1: Producto y fecha -->
                    <Card v-show="activeStep.id === 'date'">
                        <CardContent class="space-y-4 p-4 sm:p-6">
                            <div v-if="!tourIsPreselected" class="space-y-1.5">
                                <Label for="departure-tour">{{
                                    $t('Producto *')
                                }}</Label>
                                <Select
                                    :model-value="
                                        selectedTourId !== null
                                            ? String(selectedTourId)
                                            : undefined
                                    "
                                    @update:model-value="
                                        (value) => {
                                            if (typeof value === 'string') {
                                                selectedTourId = Number(value);
                                            }
                                        }
                                    "
                                >
                                    <SelectTrigger
                                        id="departure-tour"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            :placeholder="
                                                $t('Selecciona un producto')
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem
                                                v-for="tour in props.tours"
                                                :key="tour.id"
                                                :value="String(tour.id)"
                                            >
                                                {{ tour.name }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="errors.tour"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.tour }}
                                </p>
                            </div>

                            <template v-if="selectedTour">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <Label for="date-starts-at">{{
                                            $t('Inicio *')
                                        }}</Label>
                                        <Input
                                            id="date-starts-at"
                                            v-model="form.starts_at"
                                            type="datetime-local"
                                            :disabled="startsAtLocked"
                                        />
                                        <p
                                            v-if="errors.starts_at"
                                            class="text-xs text-destructive"
                                        >
                                            {{ errors.starts_at }}
                                        </p>
                                        <p
                                            v-else-if="startsAtLocked"
                                            class="text-xs text-brand-warn"
                                        >
                                            {{
                                                $t(
                                                    'Esta salida ya tiene reservas: el inicio no se puede mover.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="space-y-1.5">
                                        <Label>{{ $t('Fin') }}</Label>
                                        <p
                                            class="flex h-10 items-center rounded-md border border-dashed border-input px-3 text-sm text-muted-foreground"
                                        >
                                            {{ derivedEndLabel }}
                                        </p>
                                    </div>

                                    <div class="space-y-1.5 sm:col-span-2">
                                        <Label for="date-booking-closes-at">
                                            {{ $t('Cierre de reservas') }}
                                        </Label>
                                        <Input
                                            id="date-booking-closes-at"
                                            v-model="form.booking_closes_at"
                                            type="datetime-local"
                                        />
                                        <p
                                            v-if="errors.booking_closes_at"
                                            class="text-xs text-destructive"
                                        >
                                            {{ errors.booking_closes_at }}
                                        </p>
                                        <p
                                            v-else-if="
                                                form.booking_closes_at === '' &&
                                                inheritedBookingClosesLabel
                                            "
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ inheritedBookingClosesLabel }}
                                        </p>
                                        <p
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                $t(
                                                    'Vacío: hereda la regla de la agencia (o hasta la hora de salida si no tiene).',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                            <p
                                v-else
                                class="rounded-lg border border-dashed border-input p-4 text-sm text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Elige un producto para continuar con la fecha.',
                                    )
                                }}
                            </p>

                            <div
                                class="flex items-center justify-end gap-2 pt-2"
                            >
                                <Button
                                    type="button"
                                    :disabled="!selectedTour"
                                    @click="goToStep(activeStepIndex + 1)"
                                >
                                    {{ $t('Siguiente') }}
                                    <ArrowRight class="size-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paso 2: Cupos y precio -->
                    <Card v-show="activeStep.id === 'pricing'">
                        <CardContent class="space-y-4 p-4 sm:p-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label for="date-capacity">{{
                                        $t('Capacidad *')
                                    }}</Label>
                                    <Input
                                        id="date-capacity"
                                        v-model.number="form.capacity"
                                        type="number"
                                        min="1"
                                        max="500"
                                    />
                                    <p
                                        v-if="errors.capacity"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.capacity }}
                                    </p>
                                    <p
                                        v-else-if="
                                            isEditing &&
                                            (props.tourDate?.booked_count ??
                                                0) > 0
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'No puede ser menor que las :count reservas actuales.',
                                                {
                                                    count:
                                                        props.tourDate
                                                            ?.booked_count ?? 0,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="date-price">{{
                                        $t('Precio propio')
                                    }}</Label>
                                    <Input
                                        id="date-price"
                                        v-model="form.price_override"
                                        type="text"
                                        inputmode="decimal"
                                        :placeholder="
                                            $t(
                                                'Usa el precio base si se deja vacío',
                                            )
                                        "
                                    />
                                    <p
                                        v-if="errors.price_override"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.price_override }}
                                    </p>
                                    <p
                                        v-else-if="basePriceLabel"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'Precio base del producto: :price',
                                                {
                                                    price: basePriceLabel,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1.5 sm:col-span-2">
                                    <Label for="date-min-payment-pct">
                                        {{ $t('Mínimo de abono (%)') }}
                                    </Label>
                                    <Input
                                        id="date-min-payment-pct"
                                        v-model="form.min_payment_pct"
                                        type="number"
                                        min="1"
                                        max="100"
                                        step="1"
                                        inputmode="numeric"
                                        :placeholder="
                                            agencyMinPaymentPct !== null
                                                ? $t('Por defecto: :percent%', {
                                                      percent:
                                                          agencyMinPaymentPct,
                                                  })
                                                : undefined
                                        "
                                    />
                                    <p
                                        v-if="errors.min_payment_pct"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.min_payment_pct }}
                                    </p>
                                    <p
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'Porcentaje del total que el viajero debe abonar para asegurar esta salida.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="goToStep(activeStepIndex - 1)"
                                >
                                    <ChevronLeft class="size-4" />
                                    {{ $t('Anterior') }}
                                </Button>
                                <Button
                                    type="button"
                                    @click="goToStep(activeStepIndex + 1)"
                                >
                                    {{ $t('Siguiente') }}
                                    <ArrowRight class="size-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paso 3: Guía y ruta -->
                    <Card v-show="activeStep.id === 'guide'">
                        <CardContent class="space-y-4 p-4 sm:p-6">
                            <GuideSelect
                                id="date-guide"
                                v-model="form.guide_id"
                                :range="guideRange"
                                :exclude-tour-date-id="
                                    props.tourDate?.id ?? null
                                "
                                :fallback-guides="props.departureOptions.guides"
                                :error="errors.guide_id"
                            />

                            <div
                                class="grid gap-4"
                                :class="
                                    logisticsEnabled ? 'sm:grid-cols-2' : ''
                                "
                            >
                                <div class="space-y-1.5">
                                    <Label for="date-route">{{
                                        $t('Ruta')
                                    }}</Label>
                                    <select
                                        id="date-route"
                                        :value="form.route_id ?? ''"
                                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        @change="
                                            form.route_id = parseSelectId(
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="">
                                            {{ $t('Sin ruta') }}
                                        </option>
                                        <option
                                            v-for="route in selectedTour?.routes ??
                                            []"
                                            :key="route.id"
                                            :value="route.id"
                                        >
                                            {{ route.name }}
                                            <template v-if="route.is_default">
                                                · {{ $t('predeterminada') }}
                                            </template>
                                        </option>
                                    </select>
                                    <p
                                        v-if="errors.route_id"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.route_id }}
                                    </p>
                                    <p
                                        v-else-if="
                                            (selectedTour?.routes.length ??
                                                0) === 0
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t(
                                                'Este producto todavía no tiene rutas asociadas.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="logisticsEnabled"
                                    class="space-y-1.5"
                                >
                                    <Label for="date-provider">{{
                                        $t('Proveedor')
                                    }}</Label>
                                    <select
                                        id="date-provider"
                                        :value="form.provider_id ?? ''"
                                        class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                        @change="
                                            form.provider_id = parseSelectId(
                                                (
                                                    $event.target as HTMLSelectElement
                                                ).value,
                                            )
                                        "
                                    >
                                        <option value="">
                                            {{ $t('Sin proveedor') }}
                                        </option>
                                        <option
                                            v-for="provider in props
                                                .departureOptions.providers"
                                            :key="provider.id"
                                            :value="provider.id"
                                        >
                                            {{ provider.name }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="errors.provider_id"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.provider_id }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="logisticsEnabled" class="space-y-1.5">
                                <Label>{{ $t('Hoteles') }}</Label>
                                <p
                                    v-if="
                                        props.departureOptions.hotels.length ===
                                        0
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        $t(
                                            'No hay hoteles en tu catálogo todavía.',
                                        )
                                    }}
                                </p>
                                <div
                                    v-else
                                    class="max-h-40 space-y-1 overflow-y-auto rounded-md border border-input p-2"
                                >
                                    <button
                                        v-for="hotel in props.departureOptions
                                            .hotels"
                                        :key="hotel.id"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm transition hover:bg-muted"
                                        @click="toggleHotel(hotel.id)"
                                    >
                                        <Checkbox
                                            :model-value="
                                                form.hotel_ids.includes(
                                                    hotel.id,
                                                )
                                            "
                                            class="pointer-events-none"
                                        />
                                        <span>{{ hotel.name }}</span>
                                    </button>
                                </div>
                                <p
                                    v-if="errors.hotel_ids"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.hotel_ids }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="date-notes">{{
                                    $t('Notas')
                                }}</Label>
                                <Textarea
                                    id="date-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    :placeholder="
                                        $t(
                                            'Detalles internos de esta salida (opcional)',
                                        )
                                    "
                                />
                                <p
                                    v-if="errors.notes"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.notes }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="goToStep(activeStepIndex - 1)"
                                >
                                    <ChevronLeft class="size-4" />
                                    {{ $t('Anterior') }}
                                </Button>
                                <Button
                                    type="button"
                                    @click="goToStep(activeStepIndex + 1)"
                                >
                                    {{ $t('Siguiente') }}
                                    <ArrowRight class="size-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paso 4: Contenido de la salida -->
                    <Card v-show="activeStep.id === 'content'">
                        <CardContent class="space-y-4 p-4 sm:p-6">
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Sin personalizar, esta salida muestra siempre lo que tenga el producto en ese momento.',
                                    )
                                }}
                            </p>

                            <!-- Itinerario -->
                            <div
                                class="space-y-2 rounded-lg border border-input p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label class="text-sm">{{
                                        $t('Itinerario')
                                    }}</Label>
                                    <label
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('Personalizar para esta salida')
                                        }}
                                        <Switch
                                            :model-value="customize.itinerary"
                                            @update:model-value="
                                                (v) =>
                                                    (customize.itinerary = !!v)
                                            "
                                        />
                                    </label>
                                </div>

                                <template v-if="customize.itinerary">
                                    <div
                                        v-for="(step, index) in draft.itinerary"
                                        :key="index"
                                        class="space-y-1.5 rounded-md border border-border p-2"
                                    >
                                        <div
                                            class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_140px_auto]"
                                        >
                                            <Input
                                                v-model="step.title"
                                                maxlength="120"
                                                :placeholder="
                                                    $t('Título del paso')
                                                "
                                            />
                                            <Input
                                                v-model="step.duration_label"
                                                maxlength="30"
                                                :placeholder="$t('Duración')"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                :aria-label="
                                                    $t('Eliminar paso')
                                                "
                                                @click="
                                                    removeItineraryStep(index)
                                                "
                                            >
                                                <X
                                                    class="size-4 text-destructive"
                                                />
                                            </Button>
                                        </div>
                                        <Textarea
                                            v-model="step.description"
                                            rows="2"
                                            maxlength="2000"
                                            :placeholder="$t('Descripción')"
                                        />
                                        <p
                                            v-if="
                                                errors[
                                                    `itinerary.${index}.title`
                                                ]
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                errors[
                                                    `itinerary.${index}.title`
                                                ]
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="addItineraryStep"
                                    >
                                        <Plus class="size-3.5" />
                                        {{ $t('Agregar paso') }}
                                    </Button>
                                    <p
                                        v-if="errors.itinerary"
                                        class="text-xs text-destructive"
                                    >
                                        {{ errors.itinerary }}
                                    </p>
                                </template>
                                <p v-else class="text-xs text-muted-foreground">
                                    {{
                                        (selectedTour?.departure_defaults
                                            .itinerary.length ?? 0) > 0
                                            ? $t(
                                                  'Hereda el itinerario del producto (:count pasos).',
                                                  {
                                                      count:
                                                          selectedTour
                                                              ?.departure_defaults
                                                              .itinerary
                                                              .length ?? 0,
                                                  },
                                              )
                                            : $t(
                                                  'El producto todavía no tiene itinerario.',
                                              )
                                    }}
                                </p>
                            </div>

                            <!-- Incluye -->
                            <div
                                class="space-y-2 rounded-lg border border-input p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label class="text-sm">{{
                                        $t('Incluye')
                                    }}</Label>
                                    <label
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('Personalizar para esta salida')
                                        }}
                                        <Switch
                                            :model-value="customize.includes"
                                            @update:model-value="
                                                (v) =>
                                                    (customize.includes = !!v)
                                            "
                                        />
                                    </label>
                                </div>
                                <ChipsInput
                                    v-if="customize.includes"
                                    id="date-includes"
                                    :label="$t('Incluye')"
                                    v-model="draft.includes"
                                    :error="errors.includes"
                                />
                                <p v-else class="text-xs text-muted-foreground">
                                    {{
                                        (selectedTour?.departure_defaults
                                            .includes.length ?? 0) > 0
                                            ? selectedTour?.departure_defaults.includes.join(
                                                  ', ',
                                              )
                                            : $t(
                                                  'El producto todavía no tiene "incluye".',
                                              )
                                    }}
                                </p>
                            </div>

                            <!-- No incluye -->
                            <div
                                class="space-y-2 rounded-lg border border-input p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label class="text-sm">{{
                                        $t('No incluye')
                                    }}</Label>
                                    <label
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('Personalizar para esta salida')
                                        }}
                                        <Switch
                                            :model-value="customize.excludes"
                                            @update:model-value="
                                                (v) =>
                                                    (customize.excludes = !!v)
                                            "
                                        />
                                    </label>
                                </div>
                                <ChipsInput
                                    v-if="customize.excludes"
                                    id="date-excludes"
                                    :label="$t('No incluye')"
                                    v-model="draft.excludes"
                                    :error="errors.excludes"
                                />
                                <p v-else class="text-xs text-muted-foreground">
                                    {{
                                        (selectedTour?.departure_defaults
                                            .excludes.length ?? 0) > 0
                                            ? selectedTour?.departure_defaults.excludes.join(
                                                  ', ',
                                              )
                                            : $t(
                                                  'El producto todavía no tiene "no incluye".',
                                              )
                                    }}
                                </p>
                            </div>

                            <!-- Qué llevar -->
                            <div
                                class="space-y-2 rounded-lg border border-input p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label class="text-sm">{{
                                        $t('Qué llevar')
                                    }}</Label>
                                    <label
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('Personalizar para esta salida')
                                        }}
                                        <Switch
                                            :model-value="
                                                customize.requirements
                                            "
                                            @update:model-value="
                                                (v) =>
                                                    (customize.requirements =
                                                        !!v)
                                            "
                                        />
                                    </label>
                                </div>
                                <ChipsInput
                                    v-if="customize.requirements"
                                    id="date-requirements"
                                    :label="$t('Qué llevar')"
                                    v-model="draft.requirements"
                                    :error="errors.requirements"
                                />
                                <p v-else class="text-xs text-muted-foreground">
                                    {{
                                        (selectedTour?.departure_defaults
                                            .requirements.length ?? 0) > 0
                                            ? selectedTour?.departure_defaults.requirements.join(
                                                  ', ',
                                              )
                                            : $t(
                                                  'El producto todavía no tiene "qué llevar".',
                                              )
                                    }}
                                </p>
                            </div>

                            <!-- Punto de encuentro -->
                            <div
                                class="space-y-2 rounded-lg border border-input p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <Label
                                        class="text-sm"
                                        for="date-meeting-point"
                                    >
                                        {{ $t('Punto de encuentro') }}
                                    </Label>
                                    <label
                                        class="flex items-center gap-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            $t('Personalizar para esta salida')
                                        }}
                                        <Switch
                                            :model-value="
                                                customize.meetingPoint
                                            "
                                            @update:model-value="
                                                (v) =>
                                                    (customize.meetingPoint =
                                                        !!v)
                                            "
                                        />
                                    </label>
                                </div>
                                <Input
                                    v-if="customize.meetingPoint"
                                    id="date-meeting-point"
                                    v-model="draft.meetingPoint"
                                    maxlength="255"
                                    :placeholder="
                                        $t('Punto de encuentro de esta salida')
                                    "
                                />
                                <p
                                    v-if="errors.meeting_point"
                                    class="text-xs text-destructive"
                                >
                                    {{ errors.meeting_point }}
                                </p>
                                <p
                                    v-else-if="!customize.meetingPoint"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        selectedTour?.departure_defaults
                                            .meeting_point ??
                                        $t(
                                            'El producto todavía no tiene punto de encuentro.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="goToStep(activeStepIndex - 1)"
                                >
                                    <ChevronLeft class="size-4" />
                                    {{ $t('Anterior') }}
                                </Button>
                                <Button
                                    type="button"
                                    @click="goToStep(activeStepIndex + 1)"
                                >
                                    {{ $t('Siguiente') }}
                                    <ArrowRight class="size-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Paso 5: Revisar y guardar -->
                    <Card v-show="activeStep.id === 'review'">
                        <CardContent class="space-y-5 p-4 sm:p-6">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">
                                        {{ $t('Producto y fecha') }}
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        @click="goToStepId('date')"
                                        >{{ $t('Editar') }}</Button
                                    >
                                </div>
                                <dl class="space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Producto') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                selectedTour?.name ??
                                                $t('Sin elegir')
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Inicio') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.starts_at
                                                    ? formatTourDate(
                                                          toIso(
                                                              form.starts_at,
                                                          ) ?? '',
                                                      )
                                                    : '—'
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Fin') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{ derivedEndLabel }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Cierre de reservas') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.booking_closes_at
                                                    ? formatTourDate(
                                                          toIso(
                                                              form.booking_closes_at,
                                                          ) ?? '',
                                                      )
                                                    : (inheritedBookingClosesLabel ??
                                                      $t('Hasta la salida'))
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">
                                        {{ $t('Cupos y precio') }}
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        @click="goToStepId('pricing')"
                                        >{{ $t('Editar') }}</Button
                                    >
                                </div>
                                <dl class="space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Capacidad') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{ form.capacity }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Precio') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.price_override
                                                    ? formatCurrency(
                                                          form.price_override,
                                                          tenantCurrency,
                                                      )
                                                    : $t(
                                                          'Precio base del producto',
                                                      )
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Mínimo de abono') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.min_payment_pct !== ''
                                                    ? `${form.min_payment_pct}%`
                                                    : $t(
                                                          'Por defecto de la agencia',
                                                      )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">
                                        {{ $t('Guía y ruta') }}
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        @click="goToStepId('guide')"
                                        >{{ $t('Editar') }}</Button
                                    >
                                </div>
                                <dl class="space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Guía') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.guide_id !== null
                                                    ? (props.departureOptions.guides.find(
                                                          (guide) =>
                                                              guide.id ===
                                                              form.guide_id,
                                                      )?.name ?? $t('Asignado'))
                                                    : $t('Por asignar')
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Ruta') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                form.route_id !== null
                                                    ? (selectedTour?.routes.find(
                                                          (route) =>
                                                              route.id ===
                                                              form.route_id,
                                                      )?.name ?? '—')
                                                    : $t('Sin ruta')
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">
                                        {{ $t('Contenido de la salida') }}
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        @click="goToStepId('content')"
                                        >{{ $t('Editar') }}</Button
                                    >
                                </div>
                                <dl class="space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Itinerario') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                customize.itinerary
                                                    ? $t('Personalizado')
                                                    : $t('Hereda del producto')
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Incluye / No incluye') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                customize.includes ||
                                                customize.excludes
                                                    ? $t('Personalizado')
                                                    : $t('Hereda del producto')
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Qué llevar') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                customize.requirements
                                                    ? $t('Personalizado')
                                                    : $t('Hereda del producto')
                                            }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-muted-foreground">
                                            {{ $t('Punto de encuentro') }}
                                        </dt>
                                        <dd class="text-right font-medium">
                                            {{
                                                customize.meetingPoint
                                                    ? $t('Personalizado')
                                                    : $t('Hereda del producto')
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <p
                                v-if="!canSave"
                                class="rounded-md bg-brand-warn/10 px-3 py-2 text-sm text-brand-warn"
                            >
                                {{
                                    $t(
                                        'Falta completar lo obligatorio antes de guardar.',
                                    )
                                }}
                            </p>

                            <div class="flex items-center justify-start pt-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="goToStep(activeStepIndex - 1)"
                                >
                                    <ChevronLeft class="size-4" />
                                    {{ $t('Anterior') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>

            <StickySaveBar>
                <template #note>
                    {{ requiredNote }}
                </template>
                <template #actions>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="cancel"
                    >
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="processing || !canSave"
                        @click="submit"
                    >
                        {{
                            processing
                                ? $t('Guardando…')
                                : isEditing
                                  ? $t('Guardar salida')
                                  : $t('Crear salida')
                        }}
                    </Button>
                </template>
            </StickySaveBar>
        </div>
    </div>
</template>
