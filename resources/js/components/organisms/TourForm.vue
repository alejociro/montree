<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CapacityInput from '@/components/molecules/CapacityInput.vue';
import DifficultySelector from '@/components/molecules/DifficultySelector.vue';
import MeetingPointPicker from '@/components/molecules/MeetingPointPicker.vue';
import PriceInput from '@/components/molecules/PriceInput.vue';
import TourItineraryBuilder from '@/components/organisms/TourItineraryBuilder.vue';
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
import type {
    SupportedCurrency,
    TourCategory,
    TourFormPayload,
    TourItineraryDraft,
} from '@/types/tour';

type Errors = Record<string, string | undefined>;

type Props = {
    modelValue: TourFormPayload;
    errors: Errors;
    categories: TourCategory[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourFormPayload): void;
}>();

const value = computed(() => props.modelValue);

function update<K extends keyof TourFormPayload>(
    key: K,
    val: TourFormPayload[K],
): void {
    emit('update:modelValue', { ...value.value, [key]: val });
}

function handleString<K extends keyof TourFormPayload>(
    key: K,
): (v: string | number) => void {
    return (v) => update(key, String(v) as TourFormPayload[K]);
}

function handleCategoryChange(raw: AcceptableValue): void {
    if (typeof raw !== 'string') {
        return;
    }

    update(
        'category_id',
        raw === 'none' ? null : (Number(raw) as TourFormPayload['category_id']),
    );
}

function handleListChange(
    key: 'includes' | 'excludes' | 'requirements',
    raw: string | number,
): void {
    const items = String(raw)
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);
    update(key, items);
}

function handleMeetingPoint(meeting: {
    meeting_point: string;
    meeting_latitude: string;
    meeting_longitude: string;
}): void {
    emit('update:modelValue', {
        ...value.value,
        meeting_point: meeting.meeting_point,
        meeting_latitude: meeting.meeting_latitude,
        meeting_longitude: meeting.meeting_longitude,
    });
}

function handleItinerary(steps: TourItineraryDraft[]): void {
    update('itinerary', steps);
}

const meetingValue = computed(() => ({
    meeting_point: value.value.meeting_point,
    meeting_latitude: value.value.meeting_latitude,
    meeting_longitude: value.value.meeting_longitude,
}));

const meetingErrors = computed(() => ({
    meeting_point: props.errors.meeting_point,
    meeting_latitude: props.errors.meeting_latitude,
    meeting_longitude: props.errors.meeting_longitude,
}));
</script>

<template>
    <div class="space-y-10">
        <section class="space-y-4">
            <Heading
                variant="small"
                title="Información general"
                description="Nombre y descripción del tour."
            />

            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    :model-value="value.name"
                    maxlength="120"
                    placeholder="Sendero del Quindío"
                    @update:model-value="handleString('name')"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="short_description">Resumen corto</Label>
                <Input
                    id="short_description"
                    :model-value="value.short_description"
                    maxlength="280"
                    placeholder="Caminata de 6 horas por el valle de Cocora"
                    @update:model-value="handleString('short_description')"
                />
                <p class="text-xs text-muted-foreground">
                    Aparece en listados. Máx. 280 caracteres.
                </p>
                <InputError :message="errors.short_description" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Descripción completa</Label>
                <Textarea
                    id="description"
                    :model-value="value.description"
                    rows="6"
                    maxlength="10000"
                    placeholder="Detalle de la experiencia"
                    @update:model-value="handleString('description')"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="category_id">Categoría</Label>
                <Select
                    :model-value="
                        value.category_id === null
                            ? 'none'
                            : String(value.category_id)
                    "
                    @update:model-value="handleCategoryChange"
                >
                    <SelectTrigger id="category_id" class="w-full">
                        <SelectValue placeholder="Sin categoría" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="none">Sin categoría</SelectItem>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <InputError :message="errors.category_id" />
            </div>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Precio y capacidad"
                description="Configurá la economía del tour."
            />

            <div class="grid gap-6 md:grid-cols-2">
                <PriceInput
                    id="base_price"
                    label="Precio base por persona"
                    :model-value="value.base_price"
                    :currency="value.currency as SupportedCurrency"
                    :price-error="errors.base_price"
                    :currency-error="errors.currency"
                    @update:model-value="(v) => update('base_price', v)"
                    @update:currency="(v) => update('currency', v)"
                />

                <CapacityInput
                    id="default_capacity"
                    label="Capacidad por fecha"
                    description="Cuántos viajeros pueden ir como máximo en cada salida."
                    :model-value="value.default_capacity"
                    :error="errors.default_capacity"
                    @update:model-value="(v) => update('default_capacity', v)"
                />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="duration_hours">Duración (horas)</Label>
                    <Input
                        id="duration_hours"
                        type="number"
                        min="1"
                        max="240"
                        :model-value="value.duration_hours"
                        @update:model-value="
                            (v) => update('duration_hours', Number(v) || 1)
                        "
                    />
                    <InputError :message="errors.duration_hours" />
                </div>

                <DifficultySelector
                    :model-value="value.difficulty"
                    :error="errors.difficulty"
                    @update:model-value="(v) => update('difficulty', v)"
                />
            </div>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Detalle de la experiencia"
                description="Qué incluye, qué no y qué necesitan los viajeros."
            />

            <div class="grid gap-6 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="includes">Incluye</Label>
                    <Textarea
                        id="includes"
                        rows="5"
                        placeholder="Una entrada por línea"
                        :model-value="value.includes.join('\n')"
                        @update:model-value="
                            (v) => handleListChange('includes', v)
                        "
                    />
                    <InputError :message="errors.includes" />
                </div>

                <div class="grid gap-2">
                    <Label for="excludes">No incluye</Label>
                    <Textarea
                        id="excludes"
                        rows="5"
                        placeholder="Una entrada por línea"
                        :model-value="value.excludes.join('\n')"
                        @update:model-value="
                            (v) => handleListChange('excludes', v)
                        "
                    />
                    <InputError :message="errors.excludes" />
                </div>

                <div class="grid gap-2">
                    <Label for="requirements">Requerimientos</Label>
                    <Textarea
                        id="requirements"
                        rows="5"
                        placeholder="Una entrada por línea"
                        :model-value="value.requirements.join('\n')"
                        @update:model-value="
                            (v) => handleListChange('requirements', v)
                        "
                    />
                    <InputError :message="errors.requirements" />
                </div>
            </div>
        </section>

        <MeetingPointPicker
            :model-value="meetingValue"
            :errors="meetingErrors"
            @update:model-value="handleMeetingPoint"
        />

        <TourItineraryBuilder
            :model-value="value.itinerary"
            :errors="errors"
            @update:model-value="handleItinerary"
        />
    </div>
</template>
