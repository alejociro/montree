<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import Heading from '@/components/Heading.vue';
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
import { useTranslations } from '@/composables/useTranslations';
import { emptyTourStopDraft, TOUR_STOP_KINDS } from '@/lib/tour-stops';
import type {
    TourItineraryDraft,
    TourStopDraft,
    TourStopKind,
} from '@/types/tour';

type Props = {
    modelValue: TourStopDraft[];
    steps: TourItineraryDraft[];
    errors?: Record<string, string | undefined>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourStopDraft[]): void;
}>();

const { t } = useTranslations();

const kindLabels: Record<TourStopKind, string> = {
    pickup: t('Recogida'),
    site: t('Parada del recorrido'),
    drop: t('Regreso'),
};

function addStop(): void {
    emit('update:modelValue', [...props.modelValue, emptyTourStopDraft()]);
}

function removeStop(index: number): void {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', updated);
}

function moveStop(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.modelValue.length) {
        return;
    }

    const updated = [...props.modelValue];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    emit('update:modelValue', updated);
}

function updateStop<K extends keyof TourStopDraft>(
    index: number,
    key: K,
    value: TourStopDraft[K],
): void {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [key]: value };
    emit('update:modelValue', updated);
}

function handleText(
    index: number,
    key: Exclude<keyof TourStopDraft, 'kind'>,
    value: string | number,
): void {
    updateStop(index, key, String(value));
}

function handleKind(index: number, raw: AcceptableValue): void {
    if (typeof raw !== 'string') {
        return;
    }

    updateStop(index, 'kind', raw as TourStopKind);
}

function handleStep(index: number, raw: AcceptableValue): void {
    if (typeof raw !== 'string') {
        return;
    }

    updateStop(index, 'itinerary_step', raw === 'none' ? '' : raw);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors?.[`stops.${index}.${field}`];
}
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="$t('Ruta y puntos de encuentro')"
                :description="
                    $t(
                        'Las paradas dibujan el mapa del detalle público: recogida, recorrido y regreso, en ese orden.',
                    )
                "
            />
            <Button type="button" size="sm" variant="outline" @click="addStop">
                <Plus class="size-4" />
                {{ $t('Agregar parada') }}
            </Button>
        </div>

        <InputError :message="props.errors?.stops" />

        <div
            v-if="modelValue.length === 0"
            class="rounded-md border border-dashed border-input p-6 text-center text-sm text-muted-foreground"
        >
            {{
                $t(
                    'Sin paradas, el mapa solo muestra el punto de encuentro. Agrega la recogida, el recorrido y el regreso.',
                )
            }}
        </div>

        <div
            v-for="(stop, index) in modelValue"
            :key="index"
            class="space-y-3 rounded-lg border border-input bg-card p-4"
        >
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">
                    {{ $t('Parada') }} {{ index + 1 }}
                </span>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :disabled="index === 0"
                        @click="moveStop(index, -1)"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :disabled="index === modelValue.length - 1"
                        @click="moveStop(index, 1)"
                    >
                        <ArrowDown class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="removeStop(index)"
                    >
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[180px_1fr_140px]">
                <div class="grid gap-2">
                    <Label :for="`stop-kind-${index}`">{{ $t('Tipo') }}</Label>
                    <Select
                        :model-value="stop.kind"
                        @update:model-value="(v) => handleKind(index, v)"
                    >
                        <SelectTrigger
                            :id="`stop-kind-${index}`"
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
                                    {{ kindLabels[kind] }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="errorFor(index, 'kind')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`stop-name-${index}`">{{
                        $t('Nombre')
                    }}</Label>
                    <Input
                        :id="`stop-name-${index}`"
                        :model-value="stop.name"
                        maxlength="120"
                        :placeholder="$t('Entrada Valle de Cocora')"
                        @update:model-value="
                            (v) => handleText(index, 'name', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'name')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`stop-time-${index}`">{{ $t('Hora') }}</Label>
                    <Input
                        :id="`stop-time-${index}`"
                        :model-value="stop.time"
                        maxlength="30"
                        :placeholder="$t('8:00 a. m.')"
                        @update:model-value="
                            (v) => handleText(index, 'time', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'time')" />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="`stop-place-${index}`">{{
                        $t('Lugar')
                    }}</Label>
                    <Input
                        :id="`stop-place-${index}`"
                        :model-value="stop.place"
                        maxlength="120"
                        :placeholder="$t('Salento, Quindío')"
                        @update:model-value="
                            (v) => handleText(index, 'place', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'place')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`stop-label-${index}`">{{
                        $t('Etiqueta en el mapa')
                    }}</Label>
                    <Input
                        :id="`stop-label-${index}`"
                        :model-value="stop.label"
                        maxlength="40"
                        :placeholder="$t('Solo para recogida y regreso')"
                        @update:model-value="
                            (v) => handleText(index, 'label', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'label')" />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label :for="`stop-lat-${index}`">{{
                        $t('Latitud')
                    }}</Label>
                    <Input
                        :id="`stop-lat-${index}`"
                        :model-value="stop.latitude"
                        inputmode="decimal"
                        placeholder="4.6376"
                        @update:model-value="
                            (v) => handleText(index, 'latitude', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'latitude')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`stop-lng-${index}`">{{
                        $t('Longitud')
                    }}</Label>
                    <Input
                        :id="`stop-lng-${index}`"
                        :model-value="stop.longitude"
                        inputmode="decimal"
                        placeholder="-75.5706"
                        @update:model-value="
                            (v) => handleText(index, 'longitude', v)
                        "
                    />
                    <InputError :message="errorFor(index, 'longitude')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`stop-step-${index}`">{{
                        $t('Paso del itinerario')
                    }}</Label>
                    <Select
                        :model-value="
                            stop.itinerary_step === ''
                                ? 'none'
                                : stop.itinerary_step
                        "
                        @update:model-value="(v) => handleStep(index, v)"
                    >
                        <SelectTrigger
                            :id="`stop-step-${index}`"
                            class="w-full"
                        >
                            <SelectValue :placeholder="$t('Sin paso')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="none">{{
                                    $t('Sin paso')
                                }}</SelectItem>
                                <SelectItem
                                    v-for="step in steps"
                                    :key="step.step_number"
                                    :value="String(step.step_number)"
                                >
                                    {{ step.step_number }}. {{ step.title }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <InputError :message="errorFor(index, 'itinerary_step')" />
                </div>
            </div>
        </div>
    </section>
</template>
