<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import { computed, onMounted, ref } from 'vue';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CapacityInput from '@/components/molecules/CapacityInput.vue';
import DifficultySelector from '@/components/molecules/DifficultySelector.vue';
import MeetingPointPicker from '@/components/molecules/MeetingPointPicker.vue';
import PriceInput from '@/components/molecules/PriceInput.vue';
import TourItineraryBuilder from '@/components/organisms/TourItineraryBuilder.vue';
import TourRouteStopsBuilder from '@/components/organisms/TourRouteStopsBuilder.vue';
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

/** Recorte de `TeamMemberResource`: `roles` viaja como objetos. */
type TeamMember = { id: number; name: string; roles: { name: string }[] };

const guides = ref<{ id: number; name: string }[]>([]);

/**
 * WHY (D7): publicar el tour exige guía por defecto, así que el formulario tiene
 * que poder elegirlo. La lista sale del equipo del tenant, filtrada por rol,
 * igual que en el panel de salidas.
 */
onMounted(async () => {
    try {
        const response = await fetch(teamIndex().url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as { data: TeamMember[] };
        guides.value = payload.data
            .filter((member) =>
                (member.roles ?? []).some((role) => role.name === 'guide'),
            )
            .map((member) => ({ id: member.id, name: member.name }));
    } catch {
        guides.value = [];
    }
});

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
    <div class="space-y-10">
        <section class="space-y-4">
            <Heading
                variant="small"
                :title="$t('Información general')"
                :description="$t('Nombre y descripción del tour.')"
            />

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
                <Label for="short_description">{{ $t('Resumen corto') }}</Label>
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
                    {{ $t('Aparece en listados. Máx. 280 caracteres.') }}
                </p>
                <InputError :message="errors.short_description" />
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
                    @update:model-value="(v) => handleString('description', v)"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="category_id">{{ $t('Categoría') }}</Label>
                <Select
                    :model-value="
                        value.category_id === null
                            ? 'none'
                            : String(value.category_id)
                    "
                    @update:model-value="handleCategoryChange"
                >
                    <SelectTrigger id="category_id" class="w-full">
                        <SelectValue :placeholder="$t('Sin categoría')" />
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
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="$t('Precio y capacidad')"
                :description="$t('Configura la economía del tour.')"
            />

            <div class="grid gap-6 md:grid-cols-2">
                <PriceInput
                    id="base_price"
                    :label="$t('Precio base por persona')"
                    :model-value="value.base_price"
                    :currency="value.currency as SupportedCurrency"
                    :price-error="errors.base_price"
                    :currency-error="errors.currency"
                    @update:model-value="(v) => update('base_price', v)"
                    @update:currency="(v) => update('currency', v)"
                />

                <CapacityInput
                    id="default_capacity"
                    :label="$t('Capacidad por fecha')"
                    :description="
                        $t(
                            'Cuántos viajeros pueden ir como máximo en cada salida.',
                        )
                    "
                    :model-value="value.default_capacity"
                    :error="errors.default_capacity"
                    @update:model-value="(v) => update('default_capacity', v)"
                />
            </div>

            <div class="grid gap-6 md:grid-cols-2">
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

            <div class="grid gap-2">
                <Label for="default_guide_id">{{
                    $t('Guía por defecto')
                }}</Label>
                <select
                    id="default_guide_id"
                    :value="value.default_guide_id ?? ''"
                    class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    @change="handleDefaultGuideChange"
                >
                    <option value="">{{ $t('Sin guía por defecto') }}</option>
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
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="$t('Detalle de la experiencia')"
                :description="
                    $t('Qué incluye, qué no y qué necesitan los viajeros.')
                "
            />

            <div class="grid gap-6 md:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="includes">{{ $t('Incluye') }}</Label>
                    <Textarea
                        id="includes"
                        rows="5"
                        :placeholder="$t('Una entrada por línea')"
                        :model-value="value.includes.join('\n')"
                        @update:model-value="
                            (v) => handleListChange('includes', v)
                        "
                    />
                    <InputError :message="errors.includes" />
                </div>

                <div class="grid gap-2">
                    <Label for="excludes">{{ $t('No incluye') }}</Label>
                    <Textarea
                        id="excludes"
                        rows="5"
                        :placeholder="$t('Una entrada por línea')"
                        :model-value="value.excludes.join('\n')"
                        @update:model-value="
                            (v) => handleListChange('excludes', v)
                        "
                    />
                    <InputError :message="errors.excludes" />
                </div>

                <div class="grid gap-2">
                    <Label for="requirements">{{ $t('Requerimientos') }}</Label>
                    <Textarea
                        id="requirements"
                        rows="5"
                        :placeholder="$t('Una entrada por línea')"
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

        <TourRouteStopsBuilder
            :model-value="value.stops"
            :steps="value.itinerary"
            :errors="errors"
            @update:model-value="handleStops"
        />
    </div>
</template>
