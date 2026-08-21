<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { useTranslations } from '@/composables/useTranslations';
import type { TourItineraryDraft, TourStopDraft } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    modelValue: TourItineraryDraft[];
    /**
     * Las paradas ya ubicadas en el mapa. El enlace paso ↔ parada se edita
     * DESDE AQUÍ y no desde la parada: el orden de trabajo es lugares primero,
     * relato después, y preguntar «¿a qué paso pertenece esta parada?» mientras
     * todavía no hay pasos era una pregunta sin respuesta posible.
     */
    stops: TourStopDraft[];
    errors?: Record<string, string | undefined>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourItineraryDraft[]): void;
    (e: 'update:stops', value: TourStopDraft[]): void;
}>();

const NO_STOP = 'none';

function renumber(steps: TourItineraryDraft[]): TourItineraryDraft[] {
    return steps.map((step, index) => ({ ...step, step_number: index + 1 }));
}

/** Paradas con nombre: una sin nombre no se puede ofrecer en un select. */
const namedStops = computed(() =>
    props.stops
        .map((stop, index) => ({ stop, index }))
        .filter(({ stop }) => stop.name.trim() !== ''),
);

function stopIndexForStep(stepNumber: number): string {
    const match = props.stops.findIndex(
        (stop) => stop.itinerary_step === String(stepNumber),
    );

    return match === -1 ? NO_STOP : String(match);
}

/**
 * Enlazar un paso a una parada libera la que tuviera antes: `itinerary_step` es
 * uno a uno, y sin esto la misma parada quedaba colgando de dos pasos.
 */
function linkStop(stepNumber: number, raw: AcceptableValue): void {
    if (typeof raw !== 'string') {
        return;
    }

    const chosen = raw === NO_STOP ? null : Number(raw);

    emit(
        'update:stops',
        props.stops.map((stop, index) => {
            if (index === chosen) {
                return { ...stop, itinerary_step: String(stepNumber) };
            }

            return stop.itinerary_step === String(stepNumber)
                ? { ...stop, itinerary_step: '' }
                : stop;
        }),
    );
}

function addStep(): void {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            step_number: props.modelValue.length + 1,
            title: '',
            description: '',
            duration_label: '',
        },
    ]);
}

/**
 * Al quitar un paso los siguientes se renumeran, así que las paradas enlazadas
 * a ellos tienen que seguir el mismo desplazamiento o quedarían apuntando al
 * paso equivocado.
 */
function removeStep(index: number): void {
    const removedNumber = props.modelValue[index]?.step_number;
    const updated = [...props.modelValue];
    updated.splice(index, 1);

    emit('update:modelValue', renumber(updated));
    emit('update:stops', remapStops(removedNumber ?? null, updated));
}

function remapStops(
    removedNumber: number | null,
    remaining: TourItineraryDraft[],
): TourStopDraft[] {
    const mapping = new Map<string, string>();

    remaining.forEach((step, index) => {
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

function moveStep(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.modelValue.length) {
        return;
    }

    const updated = [...props.modelValue];
    [updated[index], updated[target]] = [updated[target], updated[index]];

    emit('update:modelValue', renumber(updated));
    emit('update:stops', remapStops(null, updated));
}

function updateStep(
    index: number,
    key: keyof TourItineraryDraft,
    value: string,
): void {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [key]: value };
    emit('update:modelValue', updated);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors?.[`itinerary.${index}.${field}`];
}

function stopLabel(index: number, stop: TourStopDraft): string {
    return t(':number. :name', { number: index + 1, name: stop.name });
}
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">
                    {{ $t('Qué pasa en el tour') }}
                </h3>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{
                        $t(
                            'El paso a paso que lee el viajero. Cada paso puede ocurrir en una de las paradas que acabas de ubicar.',
                        )
                    }}
                </p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="addStep">
                <Plus class="size-4" />
                {{ $t('Agregar paso') }}
            </Button>
        </div>

        <div
            v-if="modelValue.length === 0"
            class="rounded-xl border border-dashed border-input p-6 text-center text-sm text-muted-foreground"
        >
            {{
                $t(
                    'Aún no agregaste pasos. Empieza con la salida y la actividad principal.',
                )
            }}
        </div>

        <!--
          El hilo entre pasos va absoluto para que llegue hasta el círculo
          siguiente: como hermano flexible del número se cortaba en el borde.
        -->
        <ol>
            <li
                v-for="(step, index) in modelValue"
                :key="index"
                class="relative flex gap-3 pb-3 last:pb-0"
            >
                <span
                    v-if="index < modelValue.length - 1"
                    aria-hidden="true"
                    class="absolute top-8 bottom-0 left-3.5 w-px -translate-x-1/2 bg-border"
                />

                <span
                    class="mt-2.5 grid size-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                >
                    {{ step.step_number }}
                </span>

                <div
                    class="min-w-0 flex-1 space-y-3 rounded-xl border border-border bg-card p-3"
                >
                    <div class="flex items-start gap-2">
                        <div class="grid min-w-0 flex-1 content-start gap-2">
                            <Label :for="`step-title-${index}`" class="sr-only">
                                {{ $t('Título') }}
                            </Label>
                            <Input
                                :id="`step-title-${index}`"
                                :model-value="step.title"
                                :placeholder="$t('Salida desde la plaza')"
                                maxlength="120"
                                @update:model-value="
                                    (v) => updateStep(index, 'title', String(v))
                                "
                            />
                            <InputError :message="errorFor(index, 'title')" />
                        </div>

                        <div class="flex shrink-0 items-center gap-0.5">
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === 0"
                                :aria-label="$t('Subir')"
                                @click="moveStep(index, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="index === modelValue.length - 1"
                                :aria-label="$t('Bajar')"
                                @click="moveStep(index, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :aria-label="$t('Eliminar paso')"
                                @click="removeStep(index)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label :for="`step-duration-${index}`">
                                {{ $t('Duración') }}
                            </Label>
                            <Input
                                :id="`step-duration-${index}`"
                                :model-value="step.duration_label"
                                :placeholder="$t('30 min')"
                                maxlength="30"
                                @update:model-value="
                                    (v) =>
                                        updateStep(
                                            index,
                                            'duration_label',
                                            String(v),
                                        )
                                "
                            />
                            <InputError
                                :message="errorFor(index, 'duration_label')"
                            />
                        </div>

                        <div class="grid content-start gap-2">
                            <Label :for="`step-stop-${index}`">
                                {{ $t('Ocurre en') }}
                            </Label>
                            <Select
                                :model-value="
                                    stopIndexForStep(step.step_number)
                                "
                                :disabled="namedStops.length === 0"
                                @update:model-value="
                                    (v) => linkStop(step.step_number, v)
                                "
                            >
                                <SelectTrigger
                                    :id="`step-stop-${index}`"
                                    class="w-full"
                                >
                                    <SelectValue
                                        :placeholder="$t('Ninguna parada')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem :value="NO_STOP">
                                            {{ $t('Ninguna parada') }}
                                        </SelectItem>
                                        <SelectItem
                                            v-for="entry in namedStops"
                                            :key="entry.index"
                                            :value="String(entry.index)"
                                        >
                                            {{
                                                stopLabel(
                                                    entry.index,
                                                    entry.stop,
                                                )
                                            }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="namedStops.length === 0"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Ubica y nombra una parada arriba para poder enlazarla.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="grid content-start gap-2">
                        <Label :for="`step-description-${index}`">
                            {{ $t('Descripción') }}
                        </Label>
                        <Textarea
                            :id="`step-description-${index}`"
                            :model-value="step.description"
                            rows="2"
                            maxlength="2000"
                            :placeholder="$t('Detalles del paso')"
                            @update:model-value="
                                (v) =>
                                    updateStep(index, 'description', String(v))
                            "
                        />
                        <InputError :message="errorFor(index, 'description')" />
                    </div>
                </div>
            </li>
        </ol>
    </section>
</template>
