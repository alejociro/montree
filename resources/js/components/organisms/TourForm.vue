<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import InputError from '@/components/InputError.vue';
import CapacityInput from '@/components/molecules/CapacityInput.vue';
import ChipsInput from '@/components/molecules/ChipsInput.vue';
import DifficultySelector from '@/components/molecules/DifficultySelector.vue';
import MeetingPointPicker from '@/components/molecules/MeetingPointPicker.vue';
import PriceInput from '@/components/molecules/PriceInput.vue';
import TourItineraryBuilder from '@/components/organisms/TourItineraryBuilder.vue';
import TourRouteStopsBuilder from '@/components/organisms/TourRouteStopsBuilder.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useTenantGuides } from '@/composables/useTenantGuides';
import { categoryLabel } from '@/lib/categories';
import type {
    SupportedCurrency,
    TourCategory,
    TourFormPayload,
    TourItineraryDraft,
    TourStopDraft,
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

/**
 * WHY (D7): publicar el tour exige guía por defecto, así que el formulario
 * tiene que poder elegirlo. La lista sale del equipo del tenant, filtrada por
 * rol, igual que en el panel de salidas.
 */
const { guides } = useTenantGuides();

function handleDefaultGuideChange(event: Event): void {
    const raw = (event.target as HTMLSelectElement).value;

    update('default_guide_id', raw === '' ? null : Number(raw));
}

function update<K extends keyof TourFormPayload>(
    key: K,
    val: TourFormPayload[K],
): void {
    emit('update:modelValue', { ...value.value, [key]: val });
}

function handleString<K extends keyof TourFormPayload>(
    key: K,
    value: string | number,
): void {
    update(key, String(value) as TourFormPayload[K]);
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

function handleStops(stops: TourStopDraft[]): void {
    update('stops', stops);
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
    <div class="space-y-4">
        <section id="tour-block-general" class="scroll-mt-24">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{
                                $t('Información general')
                            }}</CardTitle>
                            <CardDescription>{{
                                $t('Cómo se presenta el tour en el catálogo.')
                            }}</CardDescription>
                        </div>
                        <MonoLabel class="shrink-0 pt-1">{{
                            $t('Paso :number', { number: 1 })
                        }}</MonoLabel>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('Nombre') }}</Label>
                        <Input
                            id="name"
                            :model-value="value.name"
                            maxlength="120"
                            :placeholder="$t('Sendero del Quindío')"
                            @update:model-value="(v) => handleString('name', v)"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="short_description">{{
                            $t('Resumen corto')
                        }}</Label>
                        <Input
                            id="short_description"
                            :model-value="value.short_description"
                            maxlength="280"
                            :placeholder="
                                $t('Caminata de 6 horas por el valle de Cocora')
                            "
                            @update:model-value="
                                (v) => handleString('short_description', v)
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            {{
                                $t('Aparece en listados. Máx. 280 caracteres.')
                            }}
                        </p>
                        <InputError :message="errors.short_description" />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="category_id">{{
                                $t('Categoría')
                            }}</Label>
                            <Select
                                :model-value="
                                    value.category_id === null
                                        ? 'none'
                                        : String(value.category_id)
                                "
                                @update:model-value="handleCategoryChange"
                            >
                                <SelectTrigger id="category_id" class="w-full">
                                    <SelectValue
                                        :placeholder="$t('Sin categoría')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="none">{{
                                            $t('Sin categoría')
                                        }}</SelectItem>
                                        <SelectItem
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="String(category.id)"
                                        >
                                            {{ categoryLabel(category.name) }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="default_guide_id">{{
                                $t('Guía por defecto')
                            }}</Label>
                            <select
                                id="default_guide_id"
                                :value="value.default_guide_id ?? ''"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @change="handleDefaultGuideChange"
                            >
                                <option value="" disabled>
                                    {{ $t('Elige un guía') }}
                                </option>
                                <option
                                    v-for="guide in guides"
                                    :key="guide.id"
                                    :value="guide.id"
                                >
                                    {{ guide.name }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Se propone al programar cada salida. Es obligatorio para publicar el tour.',
                                    )
                                }}
                            </p>
                            <InputError :message="errors.default_guide_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">{{
                            $t('Descripción completa')
                        }}</Label>
                        <Textarea
                            id="description"
                            :model-value="value.description"
                            rows="6"
                            maxlength="10000"
                            :placeholder="$t('Detalle de la experiencia')"
                            @update:model-value="
                                (v) => handleString('description', v)
                            "
                        />
                        <InputError :message="errors.description" />
                    </div>
                </CardContent>
            </Card>
        </section>

        <section id="tour-block-pricing" class="scroll-mt-24">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{
                                $t('Precio, cupo y exigencia')
                            }}</CardTitle>
                            <CardDescription>{{
                                $t('La economía y el perfil físico del tour.')
                            }}</CardDescription>
                        </div>
                        <MonoLabel class="shrink-0 pt-1">{{
                            $t('Paso :number', { number: 2 })
                        }}</MonoLabel>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-3">
                        <PriceInput
                            id="base_price"
                            :label="$t('Precio por persona')"
                            :model-value="value.base_price"
                            :currency="value.currency as SupportedCurrency"
                            :price-error="errors.base_price"
                            :currency-error="errors.currency"
                            @update:model-value="(v) => update('base_price', v)"
                            @update:currency="(v) => update('currency', v)"
                        />

                        <CapacityInput
                            id="default_capacity"
                            :label="$t('Cupo por salida')"
                            :description="
                                $t('Máximo de viajeros por cada fecha.')
                            "
                            :model-value="value.default_capacity"
                            :error="errors.default_capacity"
                            @update:model-value="
                                (v) => update('default_capacity', v)
                            "
                        />

                        <div class="grid gap-2">
                            <Label for="duration_hours">{{
                                $t('Duración (horas)')
                            }}</Label>
                            <Input
                                id="duration_hours"
                                type="number"
                                min="1"
                                max="240"
                                :model-value="value.duration_hours"
                                @update:model-value="
                                    (v) =>
                                        update('duration_hours', Number(v) || 1)
                                "
                            />
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Define cuántos días le ocupa la salida al guía.',
                                    )
                                }}
                            </p>
                            <InputError :message="errors.duration_hours" />
                        </div>
                    </div>

                    <DifficultySelector
                        :model-value="value.difficulty"
                        :error="errors.difficulty"
                        @update:model-value="(v) => update('difficulty', v)"
                    />
                </CardContent>
            </Card>
        </section>

        <section id="tour-block-detail" class="scroll-mt-24">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{
                                $t('Detalle de la experiencia')
                            }}</CardTitle>
                            <CardDescription>{{
                                $t(
                                    'Cada entrada se vuelve un ítem del listado público.',
                                )
                            }}</CardDescription>
                        </div>
                        <MonoLabel class="shrink-0 pt-1">{{
                            $t('Paso :number', { number: 3 })
                        }}</MonoLabel>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <ChipsInput
                        id="includes"
                        :label="$t('Incluye')"
                        :model-value="value.includes"
                        :placeholder="$t('Guía certificado')"
                        :error="errors.includes"
                        @update:model-value="(v) => update('includes', v)"
                    />
                    <ChipsInput
                        id="excludes"
                        :label="$t('No incluye')"
                        :model-value="value.excludes"
                        :placeholder="$t('Propinas')"
                        :error="errors.excludes"
                        @update:model-value="(v) => update('excludes', v)"
                    />
                    <ChipsInput
                        id="requirements"
                        :label="$t('Requisitos')"
                        :model-value="value.requirements"
                        :placeholder="$t('Calzado resistente')"
                        :error="errors.requirements"
                        @update:model-value="(v) => update('requirements', v)"
                    />
                </CardContent>
            </Card>
        </section>

        <section id="tour-block-route" class="scroll-mt-24">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{
                                $t('Ruta, itinerario y punto de encuentro')
                            }}</CardTitle>
                            <CardDescription>{{
                                $t(
                                    'El orden dibuja la ruta pública: recogida, recorrido y regreso.',
                                )
                            }}</CardDescription>
                        </div>
                        <MonoLabel class="shrink-0 pt-1">{{
                            $t('Paso :number', { number: 4 })
                        }}</MonoLabel>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <MeetingPointPicker
                        :model-value="meetingValue"
                        :errors="meetingErrors"
                        @update:model-value="handleMeetingPoint"
                    />

                    <div class="border-t border-brand-line-2 pt-6">
                        <TourItineraryBuilder
                            :model-value="value.itinerary"
                            :errors="errors"
                            @update:model-value="handleItinerary"
                        />
                    </div>

                    <div class="border-t border-brand-line-2 pt-6">
                        <TourRouteStopsBuilder
                            :model-value="value.stops"
                            :steps="value.itinerary"
                            :errors="errors"
                            @update:model-value="handleStops"
                        />
                    </div>
                </CardContent>
            </Card>
        </section>

        <section id="tour-block-gallery" class="scroll-mt-24">
            <slot name="gallery" />
        </section>
    </div>
</template>
