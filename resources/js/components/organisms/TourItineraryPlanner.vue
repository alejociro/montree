<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    MapPin,
    Plus,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import InputError from '@/components/InputError.vue';
import TourPlaceField from '@/components/molecules/TourPlaceField.vue';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import type { EditablePoint } from '@/composables/useEditableMap';
import { useEditableMap } from '@/composables/useEditableMap';
import { useTranslations } from '@/composables/useTranslations';
import { readableInk } from '@/lib/color';
import type { SavedPlace, StopEntry } from '@/lib/tour-itinerary';
import {
    groupStopsBySteps,
    orphanStops,
    placeKey,
    savedPlacesOf,
    sharedPlaces,
} from '@/lib/tour-itinerary';
import { routeColor } from '@/lib/tour-route';
import { isStopOrderValid, sortStopsByKind } from '@/lib/tour-route-order';
import { emptyTourStopDraft, TOUR_STOP_KINDS } from '@/lib/tour-stops';
import type { GeocodedPlace, MapPoint } from '@/types/geocoding';
import type {
    TourItineraryDraft,
    TourStopDraft,
    TourStopKind,
} from '@/types/tour';

/**
 * Constructor «Itinerario y paradas» (handoff 09).
 *
 * Un solo mapa arriba —antes había dos, el del editor y el de vista previa, con
 * los mismos pines— y debajo el itinerario: cada paso es una tarjeta que
 * contiene SUS paradas, con «Agregar parada aquí». Antes las paradas vivían en
 * una lista plana, desconectada de los pasos, y no había forma de saber por
 * cuál de las dos listas empezar.
 *
 * Nunca se piden coordenadas: la ubicación se busca por dirección, se reutiliza
 * de otra parada del tour o se señala en el mapa.
 */
const { t } = useTranslations();

export type MeetingDraft = {
    meeting_point: string;
    meeting_latitude: string;
    meeting_longitude: string;
};

type Props = {
    meeting: MeetingDraft;
    steps: TourItineraryDraft[];
    stops: TourStopDraft[];
    errors?: Record<string, string | undefined>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:meeting', value: MeetingDraft): void;
    (e: 'update:steps', value: TourItineraryDraft[]): void;
    (e: 'update:stops', value: TourStopDraft[]): void;
}>();

const kindLabels: Record<TourStopKind, string> = {
    pickup: t('Recogida'),
    site: t('Parada del recorrido'),
    drop: t('Regreso'),
};

/** `0` es el punto de encuentro; las paradas van de `1` en adelante. */
const MEETING_ID = 0;

const activeId = ref<number>(MEETING_ID);
const mapContainer = ref<HTMLElement | null>(null);

function toNumber(value: string): number | null {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : null;
}

const groups = computed(() => groupStopsBySteps(props.steps, props.stops));
const orphans = computed(() => orphanStops(props.steps, props.stops));
const places = computed<SavedPlace[]>(() => savedPlacesOf(props.stops));

/**
 * Un pin por UBICACIÓN, no por parada: las que comparten punto se dibujan
 * juntas y el rótulo lleva sus números («2·3»).
 */
const points = computed<EditablePoint[]>(() => {
    const result: EditablePoint[] = [
        {
            id: MEETING_ID,
            label: 'E',
            color: routeColor('pickup'),
            latitude: toNumber(props.meeting.meeting_latitude),
            longitude: toNumber(props.meeting.meeting_longitude),
        },
    ];

    for (const place of sharedPlaces(props.stops)) {
        const [first, ...rest] = place.indexes;
        const stop = props.stops[first];

        result.push({
            id: first + 1,
            label: place.indexes.map((index) => index + 1).join('·'),
            color: routeColor(stop.kind),
            latitude: toNumber(stop.latitude),
            longitude: toNumber(stop.longitude),
            linkedIds: rest.map((index) => index + 1),
        });
    }

    return result;
});

function movePoint(id: number, position: MapPoint): void {
    const latitude = position.latitude.toFixed(6);
    const longitude = position.longitude.toFixed(6);

    if (id === MEETING_ID) {
        emit('update:meeting', {
            ...props.meeting,
            meeting_latitude: latitude,
            meeting_longitude: longitude,
        });

        return;
    }

    const point = points.value.find((item) => item.id === id);
    const moving = new Set([
        id - 1,
        ...(point?.linkedIds ?? []).map((v) => v - 1),
    ]);

    emit(
        'update:stops',
        props.stops.map((stop, index) =>
            moving.has(index) ? { ...stop, latitude, longitude } : stop,
        ),
    );
}

const { focus, fit } = useEditableMap({
    container: mapContainer,
    points,
    activeId,
    onMove: movePoint,
});

/* ------------------------------------------------------------------ Pasos */

function renumber(steps: TourItineraryDraft[]): TourItineraryDraft[] {
    return steps.map((step, index) => ({ ...step, step_number: index + 1 }));
}

/**
 * Reasigna las paradas cuando los pasos se renumeran.
 *
 * `itinerary_step` guarda el NÚMERO del paso, así que mover o quitar uno
 * desplaza los siguientes y dejaría las paradas colgando del paso equivocado.
 * Se traduce número viejo → número nuevo antes de emitir.
 */
function remapStops(
    order: TourItineraryDraft[],
    removedNumber: number | null,
): TourStopDraft[] {
    const mapping = new Map<string, string>();

    order.forEach((step, index) => {
        mapping.set(String(step.step_number), String(index + 1));
    });

    return props.stops.map((stop) => {
        if (stop.itinerary_step === '') {
            return stop;
        }

        if (
            removedNumber !== null &&
            stop.itinerary_step === String(removedNumber)
        ) {
            return { ...stop, itinerary_step: '' };
        }

        return {
            ...stop,
            itinerary_step: mapping.get(stop.itinerary_step) ?? '',
        };
    });
}

function addStep(): void {
    emit('update:steps', [
        ...props.steps,
        {
            step_number: props.steps.length + 1,
            title: '',
            description: '',
            duration_label: '',
        },
    ]);
}

function removeStep(index: number): void {
    const removedNumber = props.steps[index]?.step_number ?? null;
    const remaining = [...props.steps];
    remaining.splice(index, 1);

    emit('update:stops', remapStops(remaining, removedNumber));
    emit('update:steps', renumber(remaining));
}

function moveStep(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.steps.length) {
        return;
    }

    const reordered = [...props.steps];
    [reordered[index], reordered[target]] = [
        reordered[target],
        reordered[index],
    ];

    emit('update:stops', remapStops(reordered, null));
    emit('update:steps', renumber(reordered));
}

function updateStep(
    index: number,
    key: keyof TourItineraryDraft,
    value: string,
): void {
    const updated = [...props.steps];
    updated[index] = { ...updated[index], [key]: value };
    emit('update:steps', updated);
}

/* --------------------------------------------------------------- Paradas */

function updateStop(index: number, patch: Partial<TourStopDraft>): void {
    const updated = [...props.stops];
    updated[index] = { ...updated[index], ...patch };
    emit('update:stops', updated);
}

/**
 * Agrega una parada DENTRO de un paso. El tipo se propone según lo que falte:
 * la primera del tour es la recogida, la última que se agregue al último paso
 * suele ser el regreso; el usuario lo puede cambiar.
 */
function addStopTo(step: TourItineraryDraft): void {
    const kinds = new Set(props.stops.map((stop) => stop.kind));
    const kind: TourStopKind = kinds.has('pickup') ? 'site' : 'pickup';

    const draft = emptyTourStopDraft(kind);
    draft.itinerary_step = String(step.step_number);

    emit('update:stops', [...props.stops, draft]);
    activeId.value = props.stops.length + 1;
}

function removeStop(index: number): void {
    const updated = [...props.stops];
    updated.splice(index, 1);
    emit('update:stops', updated);
    activeId.value = MEETING_ID;
}

function moveStopWithin(
    entries: StopEntry[],
    position: number,
    direction: -1 | 1,
): void {
    const target = position + direction;

    if (target < 0 || target >= entries.length) {
        return;
    }

    const from = entries[position].index;
    const to = entries[target].index;

    const updated = [...props.stops];
    [updated[from], updated[to]] = [updated[to], updated[from]];

    emit('update:stops', updated);
    activeId.value = to + 1;
}

function handleKind(index: number, raw: AcceptableValue): void {
    if (typeof raw === 'string') {
        updateStop(index, { kind: raw as TourStopKind });
    }
}

function handleStepChange(index: number, raw: AcceptableValue): void {
    if (typeof raw === 'string') {
        updateStop(index, { itinerary_step: raw === 'none' ? '' : raw });
    }
}

/**
 * Un resultado del buscador rellena TODO lo que sabe del punto: las coordenadas
 * siempre, y el nombre y el lugar solo si estaban vacíos —quien ya escribió
 * «Entrada del sendero» no quiere que se lo pisen con el nombre oficial de la
 * vía—.
 */
function applyPlaceToStop(index: number, place: GeocodedPlace): void {
    const stop = props.stops[index];

    if (stop === undefined) {
        return;
    }

    updateStop(index, {
        name: stop.name.trim() === '' ? place.name : stop.name,
        place: place.label,
        latitude: place.latitude.toFixed(6),
        longitude: place.longitude.toFixed(6),
    });

    activeId.value = index + 1;
    focus(index + 1);
}

function reusePlaceForStop(index: number, place: SavedPlace): void {
    const stop = props.stops[index];

    if (stop === undefined) {
        return;
    }

    updateStop(index, {
        name: stop.name.trim() === '' ? place.name : stop.name,
        place: place.address,
        latitude: place.latitude,
        longitude: place.longitude,
    });

    activeId.value = index + 1;
    focus(index + 1);
}

function applyPlaceToMeeting(place: GeocodedPlace): void {
    emit('update:meeting', {
        meeting_point:
            props.meeting.meeting_point.trim() === ''
                ? place.label
                : props.meeting.meeting_point,
        meeting_latitude: place.latitude.toFixed(6),
        meeting_longitude: place.longitude.toFixed(6),
    });

    activeId.value = MEETING_ID;
    focus(MEETING_ID);
}

function reusePlaceForMeeting(place: SavedPlace): void {
    emit('update:meeting', {
        meeting_point:
            props.meeting.meeting_point.trim() === ''
                ? place.address
                : props.meeting.meeting_point,
        meeting_latitude: place.latitude,
        meeting_longitude: place.longitude,
    });

    activeId.value = MEETING_ID;
    focus(MEETING_ID);
}

/* ----------------------------------------------------------------- Varios */

const orderIsValid = computed(() => isStopOrderValid(props.stops));

function applySort(): void {
    emit('update:stops', sortStopsByKind(props.stops));
    activeId.value = MEETING_ID;
}

function stopError(index: number, field: string): string | undefined {
    return props.errors?.[`stops.${index}.${field}`];
}

function stepError(index: number, field: string): string | undefined {
    return props.errors?.[`itinerary.${index}.${field}`];
}

/** Cuántas paradas comparten la ubicación de esta, y con qué números. */
function sharedWith(index: number): number[] {
    const key = placeKey(props.stops[index]);

    if (key === null) {
        return [];
    }

    return props.stops
        .map((stop, position) => ({ key: placeKey(stop), position }))
        .filter((entry) => entry.key === key && entry.position !== index)
        .map((entry) => entry.position + 1);
}

function stopLabel(index: number): string {
    const stop = props.stops[index];

    return stop === undefined || stop.name.trim() === ''
        ? t('la parada :number', { number: index + 1 })
        : stop.name;
}

const meetingHasPoint = computed(
    () => toNumber(props.meeting.meeting_latitude) !== null,
);
</script>

<template>
    <section class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-foreground">
                    {{ $t('Itinerario y paradas') }}
                </h3>
                <p class="mt-0.5 max-w-[64ch] text-sm text-muted-foreground">
                    {{
                        $t(
                            'Cada parada pertenece a un paso del itinerario. Dos pasos pueden compartir la misma ubicación: se reutiliza el lugar ya guardado.',
                        )
                    }}
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="addStep">
                <Plus class="size-4" />
                {{ $t('Agregar paso') }}
            </Button>
        </div>

        <!-- Mapa único + leyenda -->
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_210px]">
            <div
                ref="mapContainer"
                class="h-[320px] w-full overflow-hidden rounded-xl border border-border"
                :aria-label="$t('Mapa editable de la ruta')"
                role="application"
            />
            <div
                class="flex flex-col gap-3 rounded-xl border border-border p-3.5"
            >
                <MonoLabel>{{ $t('Mapa de la ruta') }}</MonoLabel>
                <ul class="space-y-2 text-[13px]">
                    <li
                        v-for="kind in TOUR_STOP_KINDS"
                        :key="kind"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ background: routeColor(kind) }"
                        />
                        {{ kindLabels[kind] }}
                    </li>
                </ul>
                <p class="mt-auto text-xs text-muted-foreground">
                    {{
                        $t(
                            'Un pin con dos números es una ubicación compartida por varias paradas. Arrastra cualquier pin para ajustarlo.',
                        )
                    }}
                </p>
                <Button type="button" size="sm" variant="ghost" @click="fit">
                    {{ $t('Ver todo') }}
                </Button>
            </div>
        </div>

        <!-- Punto de encuentro -->
        <div
            class="rounded-xl border p-3.5 transition"
            :class="
                activeId === MEETING_ID
                    ? 'border-primary bg-primary-soft/40'
                    : 'border-border'
            "
            @click="activeId = MEETING_ID"
        >
            <div class="flex items-center gap-2">
                <span
                    class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                >
                    E
                </span>
                <MonoLabel as="span">{{ $t('Punto de encuentro') }}</MonoLabel>
            </div>

            <div class="mt-3 grid content-start gap-2">
                <Label for="meeting_point">
                    {{ $t('Indicaciones para el viajero') }}
                </Label>
                <Input
                    id="meeting_point"
                    :model-value="props.meeting.meeting_point"
                    :placeholder="$t('Plaza Cocora, frente a la iglesia')"
                    @focus="activeId = MEETING_ID"
                    @update:model-value="
                        (v) =>
                            emit('update:meeting', {
                                ...props.meeting,
                                meeting_point: String(v),
                            })
                    "
                />
                <InputError :message="props.errors?.meeting_point" />
            </div>

            <TourPlaceField
                class="mt-3"
                :name="props.meeting.meeting_point"
                :address="''"
                :latitude="props.meeting.meeting_latitude"
                :longitude="props.meeting.meeting_longitude"
                :saved-places="places"
                :target-label="$t('el punto de encuentro')"
                @select="applyPlaceToMeeting"
                @reuse="reusePlaceForMeeting"
                @focus="focus(MEETING_ID)"
            />
            <p
                v-if="!meetingHasPoint"
                class="mt-2 text-[11.5px] text-brand-warn"
            >
                {{ $t('Sin ubicación en el mapa todavía.') }}
            </p>
            <InputError :message="props.errors?.meeting_latitude" />
        </div>

        <!-- Aviso de orden -->
        <div
            v-if="!orderIsValid"
            class="flex flex-wrap items-center gap-2.5 rounded-xl border border-brand-warn/30 bg-brand-warn-50 p-3 text-xs text-brand-warn"
        >
            <TriangleAlert class="size-4 shrink-0" />
            <span class="min-w-0 flex-1">
                {{
                    $t(
                        'El orden de las paradas es el trazo del mapa público, y ahora mismo el regreso no queda al final.',
                    )
                }}
            </span>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="applySort"
            >
                {{ $t('Ordenar recogida → recorrido → regreso') }}
            </Button>
        </div>

        <InputError :message="props.errors?.stops" />
        <InputError :message="props.errors?.itinerary" />

        <!-- Pasos -->
        <div
            v-if="props.steps.length === 0"
            class="rounded-xl border border-dashed border-input p-8 text-center"
        >
            <p class="text-sm font-medium">
                {{ $t('Todavía no hay pasos') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    $t(
                        'Empieza por la salida: cada paso agrupa lo que ocurre y dónde ocurre.',
                    )
                }}
            </p>
            <Button
                type="button"
                size="sm"
                class="mt-3"
                variant="outline"
                @click="addStep"
            >
                <Plus class="size-4" />
                {{ $t('Agregar paso') }}
            </Button>
        </div>

        <ol v-else class="space-y-4">
            <li
                v-for="group in groups"
                :key="group.index"
                class="relative flex gap-3"
            >
                <!-- Hilo entre pasos: absoluto para que cruce el relleno. -->
                <span
                    v-if="group.index < groups.length - 1"
                    aria-hidden="true"
                    class="absolute top-9 -bottom-4 left-3.5 w-px -translate-x-1/2 bg-border"
                />

                <span
                    class="mt-3 grid size-7 shrink-0 place-items-center rounded-full bg-brand-ink text-xs font-bold text-brand-on-ink"
                >
                    {{ group.step.step_number }}
                </span>

                <div
                    class="min-w-0 flex-1 rounded-xl border border-border bg-card p-3.5"
                >
                    <div
                        class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_auto]"
                    >
                        <div class="grid content-start gap-2">
                            <Label :for="`step-title-${group.index}`">
                                {{ $t('Título del paso') }}
                            </Label>
                            <Input
                                :id="`step-title-${group.index}`"
                                :model-value="group.step.title"
                                :placeholder="$t('Salida desde Armenia')"
                                maxlength="120"
                                @update:model-value="
                                    (v) =>
                                        updateStep(
                                            group.index,
                                            'title',
                                            String(v),
                                        )
                                "
                            />
                            <InputError
                                :message="stepError(group.index, 'title')"
                            />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label :for="`step-duration-${group.index}`">
                                {{ $t('Duración') }}
                            </Label>
                            <Input
                                :id="`step-duration-${group.index}`"
                                :model-value="group.step.duration_label"
                                :placeholder="$t('1 h 10 min')"
                                maxlength="30"
                                @update:model-value="
                                    (v) =>
                                        updateStep(
                                            group.index,
                                            'duration_label',
                                            String(v),
                                        )
                                "
                            />
                            <InputError
                                :message="
                                    stepError(group.index, 'duration_label')
                                "
                            />
                        </div>

                        <div class="flex items-end gap-0.5 pb-0.5">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="group.index === 0"
                                :aria-label="$t('Subir paso')"
                                @click="moveStep(group.index, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="group.index === groups.length - 1"
                                :aria-label="$t('Bajar paso')"
                                @click="moveStep(group.index, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :aria-label="$t('Eliminar paso')"
                                @click="removeStep(group.index)"
                            >
                                <X class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <div class="mt-3 grid content-start gap-2">
                        <Label :for="`step-description-${group.index}`">
                            {{ $t('Descripción') }}
                        </Label>
                        <Textarea
                            :id="`step-description-${group.index}`"
                            :model-value="group.step.description"
                            rows="2"
                            maxlength="2000"
                            :placeholder="
                                $t(
                                    'Recogida en la Plaza de Bolívar y traslado hasta Salento.',
                                )
                            "
                            @update:model-value="
                                (v) =>
                                    updateStep(
                                        group.index,
                                        'description',
                                        String(v),
                                    )
                            "
                        />
                        <InputError
                            :message="stepError(group.index, 'description')"
                        />
                    </div>

                    <div
                        class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-brand-line-2 pt-3.5"
                    >
                        <MonoLabel>
                            {{
                                $t('Paradas de este paso · :count', {
                                    count: group.stops.length,
                                })
                            }}
                        </MonoLabel>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="addStopTo(group.step)"
                        >
                            <Plus class="size-3.5" />
                            {{ $t('Agregar parada aquí') }}
                        </Button>
                    </div>

                    <p
                        v-if="group.stops.length === 0"
                        class="mt-2.5 rounded-lg border border-dashed border-input px-3 py-4 text-center text-[13px] text-muted-foreground"
                    >
                        {{
                            $t(
                                'Este paso todavía no tiene una ubicación en el mapa.',
                            )
                        }}
                    </p>

                    <ul v-else class="mt-2.5 space-y-2.5">
                        <li
                            v-for="(entry, position) in group.stops"
                            :key="entry.index"
                            class="rounded-xl border p-3 transition"
                            :class="
                                activeId === entry.index + 1
                                    ? 'border-primary bg-primary-soft/30'
                                    : 'border-border'
                            "
                            @click="activeId = entry.index + 1"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-6 grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                                    :style="{
                                        background: routeColor(entry.stop.kind),
                                        color: readableInk(
                                            routeColor(entry.stop.kind),
                                        ),
                                    }"
                                >
                                    {{ entry.index + 1 }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="grid content-start gap-2">
                                        <Label
                                            :for="`stop-name-${entry.index}`"
                                        >
                                            {{ $t('Nombre de la parada') }}
                                        </Label>
                                        <Input
                                            :id="`stop-name-${entry.index}`"
                                            :model-value="entry.stop.name"
                                            maxlength="120"
                                            :placeholder="
                                                $t('Entrada Valle de Cocora')
                                            "
                                            @focus="activeId = entry.index + 1"
                                            @update:model-value="
                                                (v) =>
                                                    updateStop(entry.index, {
                                                        name: String(v),
                                                    })
                                            "
                                        />
                                        <InputError
                                            :message="
                                                stopError(entry.index, 'name')
                                            "
                                        />
                                    </div>

                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div class="grid content-start gap-2">
                                            <Label
                                                :for="`stop-kind-${entry.index}`"
                                            >
                                                {{ $t('Tipo de parada') }}
                                            </Label>
                                            <Select
                                                :model-value="entry.stop.kind"
                                                @update:model-value="
                                                    (v) =>
                                                        handleKind(
                                                            entry.index,
                                                            v,
                                                        )
                                                "
                                            >
                                                <SelectTrigger
                                                    :id="`stop-kind-${entry.index}`"
                                                    class="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem
                                                            v-for="kind in TOUR_STOP_KINDS"
                                                            :key="kind"
                                                            :value="kind"
                                                        >
                                                            {{
                                                                kindLabels[kind]
                                                            }}
                                                        </SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div class="grid content-start gap-2">
                                            <Label
                                                :for="`stop-time-${entry.index}`"
                                            >
                                                {{ $t('Hora') }}
                                            </Label>
                                            <Input
                                                :id="`stop-time-${entry.index}`"
                                                :model-value="entry.stop.time"
                                                maxlength="30"
                                                :placeholder="$t('8:00 a. m.')"
                                                @update:model-value="
                                                    (v) =>
                                                        updateStop(
                                                            entry.index,
                                                            { time: String(v) },
                                                        )
                                                "
                                            />
                                        </div>
                                    </div>

                                    <TourPlaceField
                                        class="mt-3"
                                        :name="entry.stop.name"
                                        :address="entry.stop.place"
                                        :latitude="entry.stop.latitude"
                                        :longitude="entry.stop.longitude"
                                        :saved-places="places"
                                        :own-number="entry.index + 1"
                                        :target-label="stopLabel(entry.index)"
                                        @select="
                                            (place) =>
                                                applyPlaceToStop(
                                                    entry.index,
                                                    place,
                                                )
                                        "
                                        @reuse="
                                            (place) =>
                                                reusePlaceForStop(
                                                    entry.index,
                                                    place,
                                                )
                                        "
                                        @focus="focus(entry.index + 1)"
                                    />

                                    <p
                                        v-if="
                                            sharedWith(entry.index).length > 0
                                        "
                                        class="mt-2 flex items-center gap-1.5 text-[11.5px] text-muted-foreground"
                                    >
                                        <MapPin class="size-3.5" />
                                        {{
                                            $t(
                                                'Misma ubicación que la parada :others',
                                                {
                                                    others: sharedWith(
                                                        entry.index,
                                                    ).join(', '),
                                                },
                                            )
                                        }}
                                    </p>
                                    <InputError
                                        :message="
                                            stopError(entry.index, 'latitude')
                                        "
                                    />

                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div class="grid content-start gap-2">
                                            <Label
                                                :for="`stop-place-${entry.index}`"
                                            >
                                                {{
                                                    $t(
                                                        'Lugar (referencia visible)',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="`stop-place-${entry.index}`"
                                                :model-value="entry.stop.place"
                                                maxlength="120"
                                                :placeholder="
                                                    $t('Salento, Quindío')
                                                "
                                                @update:model-value="
                                                    (v) =>
                                                        updateStop(
                                                            entry.index,
                                                            {
                                                                place: String(
                                                                    v,
                                                                ),
                                                            },
                                                        )
                                                "
                                            />
                                        </div>

                                        <!--
                                          La etiqueta solo la pintan los extremos
                                          del mapa: en una parada del recorrido no
                                          se dibuja y el campo solo confundía.
                                        -->
                                        <div class="grid content-start gap-2">
                                            <Label
                                                :for="`stop-label-${entry.index}`"
                                            >
                                                {{ $t('Etiqueta en el mapa') }}
                                            </Label>
                                            <Input
                                                :id="`stop-label-${entry.index}`"
                                                :model-value="entry.stop.label"
                                                maxlength="40"
                                                :disabled="
                                                    entry.stop.kind === 'site'
                                                "
                                                :placeholder="
                                                    $t(
                                                        'Solo para recogida y regreso',
                                                    )
                                                "
                                                @update:model-value="
                                                    (v) =>
                                                        updateStop(
                                                            entry.index,
                                                            {
                                                                label: String(
                                                                    v,
                                                                ),
                                                            },
                                                        )
                                                "
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="mt-3 grid content-start gap-2 sm:max-w-[280px]"
                                    >
                                        <Label
                                            :for="`stop-step-${entry.index}`"
                                        >
                                            {{ $t('Paso del itinerario') }}
                                        </Label>
                                        <Select
                                            :model-value="
                                                entry.stop.itinerary_step === ''
                                                    ? 'none'
                                                    : entry.stop.itinerary_step
                                            "
                                            @update:model-value="
                                                (v) =>
                                                    handleStepChange(
                                                        entry.index,
                                                        v,
                                                    )
                                            "
                                        >
                                            <SelectTrigger
                                                :id="`stop-step-${entry.index}`"
                                                class="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="none">
                                                        {{ $t('Sin paso') }}
                                                    </SelectItem>
                                                    <SelectItem
                                                        v-for="option in props.steps"
                                                        :key="
                                                            option.step_number
                                                        "
                                                        :value="
                                                            String(
                                                                option.step_number,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            option.step_number
                                                        }}.
                                                        {{
                                                            option.title ||
                                                            $t('Sin título')
                                                        }}
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-col gap-0.5">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        :disabled="position === 0"
                                        :aria-label="$t('Subir parada')"
                                        @click.stop="
                                            moveStopWithin(
                                                group.stops,
                                                position,
                                                -1,
                                            )
                                        "
                                    >
                                        <ArrowUp class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        :disabled="
                                            position === group.stops.length - 1
                                        "
                                        :aria-label="$t('Bajar parada')"
                                        @click.stop="
                                            moveStopWithin(
                                                group.stops,
                                                position,
                                                1,
                                            )
                                        "
                                    >
                                        <ArrowDown class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        :aria-label="$t('Eliminar parada')"
                                        @click.stop="removeStop(entry.index)"
                                    >
                                        <X class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>
        </ol>

        <!-- Paradas sin paso: nada se pierde al borrar un paso. -->
        <div
            v-if="orphans.length > 0"
            class="rounded-xl border border-brand-warn/30 bg-brand-warn-50 p-3.5"
        >
            <MonoLabel>{{ $t('Paradas sin paso') }}</MonoLabel>
            <p class="mt-1 text-xs text-brand-warn">
                {{
                    $t(
                        'Siguen en el mapa pero no aparecen en el itinerario. Asígnalas a un paso o elimínalas.',
                    )
                }}
            </p>
            <ul class="mt-2.5 space-y-2">
                <li
                    v-for="entry in orphans"
                    :key="entry.index"
                    class="flex flex-wrap items-center gap-2.5 rounded-lg border border-border bg-card px-3 py-2.5"
                >
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">
                        {{ stopLabel(entry.index) }}
                    </span>
                    <Select
                        :model-value="'none'"
                        @update:model-value="
                            (v) => handleStepChange(entry.index, v)
                        "
                    >
                        <SelectTrigger class="w-[200px]">
                            <SelectValue :placeholder="$t('Sin paso')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="none">
                                    {{ $t('Sin paso') }}
                                </SelectItem>
                                <SelectItem
                                    v-for="option in props.steps"
                                    :key="option.step_number"
                                    :value="String(option.step_number)"
                                >
                                    {{ option.step_number }}.
                                    {{ option.title || $t('Sin título') }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :aria-label="$t('Eliminar parada')"
                        @click="removeStop(entry.index)"
                    >
                        <X class="size-4 text-destructive" />
                    </Button>
                </li>
            </ul>
        </div>
    </section>
</template>
