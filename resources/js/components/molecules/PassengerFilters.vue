<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import { formatTourDate } from '@/lib/format';
import type { DepartureOption, PassengerSegment } from '@/types/passenger';
import { MEDICAL_SEGMENT, PASSENGER_SEGMENTS } from '@/types/passenger';

const { t } = useTranslations();

type Props = {
    segment: PassengerSegment;
    search: string;
    departures: DepartureOption[];
    tourDateId: number | null;
    canViewMedical: boolean;
    /** La zona del guía no elige salida: va en la ruta. */
    showDepartureSelect?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showDepartureSelect: true,
});

const emit = defineEmits<{
    'update:segment': [value: PassengerSegment];
    'update:search': [value: string];
    'update:tourDateId': [value: number | null];
}>();

const ALL_DEPARTURES = 'all';

const segmentLabels: Record<PassengerSegment, string> = {
    all: t('Todos'),
    due: t('Con saldo'),
    paid: t('Al día'),
    obs: t('Con observaciones'),
};

/**
 * WHY: sin `can_view_medical` el segmento «Con observaciones» NO SE OFRECE
 * (D7). Pedirlo es 403 en el backend, y ofrecerlo deshabilitado señalaría que
 * hay algo detrás — que es exactamente lo que la decisión evita.
 */
const segments = computed<PassengerSegment[]>(() =>
    PASSENGER_SEGMENTS.filter(
        (value) => value !== MEDICAL_SEGMENT || props.canViewMedical,
    ),
);

const departureValue = computed(() =>
    props.tourDateId === null ? ALL_DEPARTURES : String(props.tourDateId),
);

function onDepartureChange(value: unknown): void {
    if (value === ALL_DEPARTURES || typeof value !== 'string') {
        emit('update:tourDateId', null);

        return;
    }

    emit('update:tourDateId', Number(value));
}

function departureLabel(departure: DepartureOption): string {
    const date = formatTourDate(departure.starts_at, { withWeekday: false });

    if (!departure.guide) {
        return date;
    }

    return `${date} · ${departure.guide.name}`;
}
</script>

<template>
    <div
        class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
        <div
            class="flex flex-wrap items-center gap-1 rounded-lg border border-border bg-card p-1"
            role="group"
            :aria-label="$t('Filtrar pasajeros')"
        >
            <button
                v-for="value in segments"
                :key="value"
                type="button"
                :aria-pressed="props.segment === value"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :class="
                    props.segment === value
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                @click="emit('update:segment', value)"
            >
                {{ segmentLabels[value] }}
            </button>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div v-if="props.showDepartureSelect" class="space-y-1.5 sm:w-64">
                <Label for="passenger-departure" class="text-xs">
                    {{ $t('Salida') }}
                </Label>
                <Select
                    :model-value="departureValue"
                    @update:model-value="onDepartureChange"
                >
                    <SelectTrigger id="passenger-departure" class="w-full">
                        <SelectValue :placeholder="$t('Todas las salidas')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL_DEPARTURES">
                            {{ $t('Todas las salidas') }}
                        </SelectItem>
                        <SelectItem
                            v-for="departure in props.departures"
                            :key="departure.id"
                            :value="String(departure.id)"
                        >
                            {{ departureLabel(departure) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-1.5 sm:w-64">
                <Label for="passenger-search" class="text-xs">
                    {{ $t('Buscar') }}
                </Label>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="passenger-search"
                        :model-value="props.search"
                        type="search"
                        class="pl-8"
                        :placeholder="$t('Nombre o documento')"
                        @update:model-value="
                            emit('update:search', String($event))
                        "
                    />
                </div>
            </div>
        </div>
    </div>
</template>
