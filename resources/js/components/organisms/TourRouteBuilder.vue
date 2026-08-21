<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Crosshair,
    MapPin,
    Plus,
    Trash2,
    TriangleAlert,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import InputError from '@/components/InputError.vue';
import PlaceSearchField from '@/components/molecules/PlaceSearchField.vue';
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
import type { EditablePoint } from '@/composables/useEditableMap';
import { useEditableMap } from '@/composables/useEditableMap';
import { useTranslations } from '@/composables/useTranslations';
import { readableInk } from '@/lib/color';
import { routeColor } from '@/lib/tour-route';
import { isStopOrderValid, sortStopsByKind } from '@/lib/tour-route-order';
import { emptyTourStopDraft, TOUR_STOP_KINDS } from '@/lib/tour-stops';
import type { GeocodedPlace, MapPoint } from '@/types/geocoding';
import type { TourStopDraft, TourStopKind } from '@/types/tour';

const { t } = useTranslations();

export type MeetingDraft = {
    meeting_point: string;
    meeting_latitude: string;
    meeting_longitude: string;
};

type Props = {
    meeting: MeetingDraft;
    stops: TourStopDraft[];
    errors?: Record<string, string | undefined>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:meeting', value: MeetingDraft): void;
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

const points = computed<EditablePoint[]>(() => [
    {
        id: MEETING_ID,
        label: 'E',
        color: routeColor('pickup'),
        latitude: toNumber(props.meeting.meeting_latitude),
        longitude: toNumber(props.meeting.meeting_longitude),
    },
    ...props.stops.map((stop, index) => ({
        id: index + 1,
        label: String(index + 1),
        color: routeColor(stop.kind),
        latitude: toNumber(stop.latitude),
        longitude: toNumber(stop.longitude),
    })),
]);

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

    updateStop(id - 1, { latitude, longitude });
}

const { focus, fit } = useEditableMap({
    container: mapContainer,
    points,
    activeId,
    onMove: movePoint,
});

function updateStop(index: number, patch: Partial<TourStopDraft>): void {
    const updated = [...props.stops];
    updated[index] = { ...updated[index], ...patch };
    emit('update:stops', updated);
}

function addStop(): void {
    const kind: TourStopKind = props.stops.some(
        (stop) => stop.kind === 'pickup',
    )
        ? 'site'
        : 'pickup';

    emit('update:stops', [...props.stops, emptyTourStopDraft(kind)]);
    activeId.value = props.stops.length + 1;
}

function removeStop(index: number): void {
    const updated = [...props.stops];
    updated.splice(index, 1);
    emit('update:stops', updated);
    activeId.value = MEETING_ID;
}

function moveStop(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.stops.length) {
        return;
    }

    const updated = [...props.stops];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    emit('update:stops', updated);
    activeId.value = target + 1;
}

function handleKind(index: number, raw: AcceptableValue): void {
    if (typeof raw === 'string') {
        updateStop(index, { kind: raw as TourStopKind });
    }
}

/**
 * Un resultado del buscador rellena TODO lo que sabe del punto activo: las
 * coordenadas siempre, y el nombre y el lugar solo si estaban vacíos —quien ya
 * escribió «Entrada del sendero» no quiere que se lo pisen con el nombre
 * oficial de la vía—.
 */
function applyPlace(place: GeocodedPlace): void {
    const latitude = place.latitude.toFixed(6);
    const longitude = place.longitude.toFixed(6);

    if (activeId.value === MEETING_ID) {
        emit('update:meeting', {
            meeting_point:
                props.meeting.meeting_point.trim() === ''
                    ? place.label
                    : props.meeting.meeting_point,
            meeting_latitude: latitude,
            meeting_longitude: longitude,
        });
    } else {
        const index = activeId.value - 1;
        const stop = props.stops[index];

        if (stop === undefined) {
            return;
        }

        updateStop(index, {
            name: stop.name.trim() === '' ? place.name : stop.name,
            place: stop.place.trim() === '' ? place.label : stop.place,
            latitude,
            longitude,
        });
    }

    focus(activeId.value);
}

const activeLabel = computed(() => {
    if (activeId.value === MEETING_ID) {
        return t('el punto de encuentro');
    }

    const stop = props.stops[activeId.value - 1];

    return stop === undefined || stop.name.trim() === ''
        ? t('la parada :number', { number: activeId.value })
        : stop.name;
});

const orderIsValid = computed(() => isStopOrderValid(props.stops));

function applySort(): void {
    emit('update:stops', sortStopsByKind(props.stops));
    activeId.value = MEETING_ID;
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors?.[`stops.${index}.${field}`];
}

function hasCoordinates(id: number): boolean {
    const point = points.value.find((item) => item.id === id);

    return point !== undefined && point.latitude !== null;
}
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">
                    {{ $t('Dónde ocurre el tour') }}
                </h3>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{
                        $t(
                            'Marca primero los lugares. Escribe la dirección y elígela de la lista, o arrastra el pin en el mapa.',
                        )
                    }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button type="button" size="sm" variant="ghost" @click="fit">
                    {{ $t('Ver todo') }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="addStop"
                >
                    <Plus class="size-4" />
                    {{ $t('Agregar parada') }}
                </Button>
            </div>
        </div>

        <PlaceSearchField :target-label="activeLabel" @select="applyPlace" />

        <!--
          El mapa manda: el punto seleccionado se mueve arrastrando su pin o
          haciendo clic en cualquier parte. Las coordenadas quedan como dato de
          consulta al pie de cada fila, no como campo que haya que rellenar.
        -->
        <div
            ref="mapContainer"
            class="h-[320px] w-full overflow-hidden rounded-xl border border-border"
            :aria-label="$t('Mapa editable de la ruta')"
            role="application"
        />

        <p class="text-xs text-muted-foreground">
            {{
                $t(
                    'Haz clic en el mapa para colocar :target, o arrastra cualquier pin para corregirlo.',
                    { target: activeLabel },
                )
            }}
        </p>

        <!-- Punto de encuentro -->
        <div
            class="rounded-xl border p-3 transition"
            :class="
                activeId === MEETING_ID
                    ? 'border-primary bg-primary-soft'
                    : 'border-border'
            "
        >
            <button
                type="button"
                class="flex w-full items-center gap-2 text-left"
                :aria-pressed="activeId === MEETING_ID"
                @click="activeId = MEETING_ID"
            >
                <span
                    class="grid size-6 shrink-0 place-items-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                >
                    E
                </span>
                <MonoLabel as="span">{{ $t('Punto de encuentro') }}</MonoLabel>
            </button>

            <div class="mt-2.5 grid content-start gap-2">
                <Label for="meeting_point" class="sr-only">
                    {{ $t('Dirección o referencia') }}
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
                <p
                    v-if="hasCoordinates(MEETING_ID)"
                    class="font-mono text-[11px] text-muted-foreground"
                >
                    {{ props.meeting.meeting_latitude }},
                    {{ props.meeting.meeting_longitude }}
                </p>
                <p v-else class="text-[11px] text-brand-warn">
                    {{ $t('Sin ubicación en el mapa todavía.') }}
                </p>
                <InputError :message="props.errors?.meeting_point" />
                <InputError :message="props.errors?.meeting_latitude" />
                <InputError :message="props.errors?.meeting_longitude" />
            </div>
        </div>

        <!-- Paradas -->
        <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <MonoLabel>{{ $t('Paradas de la ruta') }}</MonoLabel>
                <Button
                    v-if="!orderIsValid"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="applySort"
                >
                    {{ $t('Ordenar recogida → recorrido → regreso') }}
                </Button>
            </div>

            <div
                v-if="!orderIsValid"
                class="flex items-start gap-2 rounded-lg border border-brand-warn/30 bg-brand-warn-50 p-2.5 text-xs text-brand-warn"
            >
                <TriangleAlert class="mt-px size-3.5 shrink-0" />
                {{
                    $t(
                        'El orden de esta lista es el trazo del mapa público, y ahora mismo el regreso no queda al final.',
                    )
                }}
            </div>

            <InputError :message="props.errors?.stops" />

            <div
                v-if="props.stops.length === 0"
                class="rounded-xl border border-dashed border-input p-6 text-center text-sm text-muted-foreground"
            >
                {{
                    $t(
                        'Sin paradas, el mapa solo muestra el punto de encuentro. Agrega la recogida, el recorrido y el regreso.',
                    )
                }}
            </div>

            <div
                v-for="(stop, index) in props.stops"
                :key="index"
                class="rounded-xl border p-3 transition"
                :class="
                    activeId === index + 1
                        ? 'border-primary bg-primary-soft'
                        : 'border-border'
                "
                @click="activeId = index + 1"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="mt-1 grid size-6 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                        :style="{
                            background: routeColor(stop.kind),
                            color: readableInk(routeColor(stop.kind)),
                        }"
                    >
                        {{ index + 1 }}
                    </span>

                    <div class="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                        <Select
                            :model-value="stop.kind"
                            @update:model-value="(v) => handleKind(index, v)"
                        >
                            <SelectTrigger
                                :id="`stop-kind-${index}`"
                                class="w-full"
                                :aria-label="$t('Tipo de parada')"
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
                                        {{ kindLabels[kind] }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                        <Input
                            :model-value="stop.name"
                            maxlength="120"
                            :placeholder="$t('Entrada Valle de Cocora')"
                            :aria-label="$t('Nombre de la parada')"
                            @focus="activeId = index + 1"
                            @update:model-value="
                                (v) => updateStop(index, { name: String(v) })
                            "
                        />

                        <Input
                            :model-value="stop.place"
                            maxlength="120"
                            :placeholder="$t('Salento, Quindío')"
                            :aria-label="$t('Dirección o lugar')"
                            @focus="activeId = index + 1"
                            @update:model-value="
                                (v) => updateStop(index, { place: String(v) })
                            "
                        />

                        <Input
                            :model-value="stop.time"
                            maxlength="30"
                            :placeholder="$t('8:00 a. m.')"
                            :aria-label="$t('Hora')"
                            @focus="activeId = index + 1"
                            @update:model-value="
                                (v) => updateStop(index, { time: String(v) })
                            "
                        />

                        <!--
                          La etiqueta solo la pintan los extremos del mapa: en
                          una parada del recorrido no se dibuja y el campo solo
                          confundía.
                        -->
                        <Input
                            v-if="stop.kind !== 'site'"
                            :model-value="stop.label"
                            maxlength="40"
                            :placeholder="$t('Etiqueta en el mapa')"
                            :aria-label="$t('Etiqueta en el mapa')"
                            @focus="activeId = index + 1"
                            @update:model-value="
                                (v) => updateStop(index, { label: String(v) })
                            "
                        />

                        <p
                            v-if="hasCoordinates(index + 1)"
                            class="font-mono text-[11px] text-muted-foreground sm:col-span-2"
                        >
                            {{ stop.latitude }}, {{ stop.longitude }}
                        </p>
                        <p
                            v-else
                            class="flex items-center gap-1 text-[11px] text-brand-warn sm:col-span-2"
                        >
                            <MapPin class="size-3" />
                            {{
                                $t(
                                    'Sin ubicación: búscala arriba o haz clic en el mapa.',
                                )
                            }}
                        </p>

                        <div class="sm:col-span-2">
                            <InputError :message="errorFor(index, 'kind')" />
                            <InputError :message="errorFor(index, 'name')" />
                            <InputError :message="errorFor(index, 'place')" />
                            <InputError :message="errorFor(index, 'time')" />
                            <InputError
                                :message="errorFor(index, 'latitude')"
                            />
                            <InputError
                                :message="errorFor(index, 'longitude')"
                            />
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col gap-0.5">
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="!hasCoordinates(index + 1)"
                            :aria-label="$t('Centrar en el mapa')"
                            @click.stop="
                                activeId = index + 1;
                                focus(index + 1);
                            "
                        >
                            <Crosshair class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="index === 0"
                            :aria-label="$t('Subir')"
                            @click.stop="moveStop(index, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :disabled="index === props.stops.length - 1"
                            :aria-label="$t('Bajar')"
                            @click.stop="moveStop(index, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            :aria-label="$t('Eliminar parada')"
                            @click.stop="removeStop(index)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
