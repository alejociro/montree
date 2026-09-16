<script setup lang="ts">
import type { AcceptableValue } from 'reka-ui';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import FilterBar from '@/components/molecules/FilterBar.vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DepartureScopeId, DepartureTourOption } from '@/types/logistics';

/**
 * Buscador, bandejas y selects del tablero de salidas. No decide nada: cada
 * cambio sube al tablero, que es quien traduce el estado a query string.
 */
type Props = {
    search: string;
    scope: DepartureScopeId;
    /** `all` o el id del producto, como string porque el select trabaja en texto. */
    tourId: string;
    direction: 'asc' | 'desc';
    tabs: CountTab[];
    resultLabel: string | null;
    tours: DepartureTourOption[];
    allToursValue: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:scope': [value: DepartureScopeId];
    'update:tourId': [value: string];
    'update:direction': [value: 'asc' | 'desc'];
}>();

function onTourChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        emit('update:tourId', value);
    }
}

function onDirectionChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        emit('update:direction', value === 'desc' ? 'desc' : 'asc');
    }
}
</script>

<template>
    <FilterBar
        search-id="departures-search"
        :search="props.search"
        :placeholder="$t('Buscar salida por tour, código o guía')"
        :result-label="props.resultLabel"
        :tabs="props.tabs"
        :active-tab="props.scope"
        :tabs-label="$t('Bandejas de salidas')"
        @update:search="emit('update:search', $event)"
        @update:active-tab="emit('update:scope', $event as DepartureScopeId)"
    >
        <template #selects>
            <div class="flex items-center gap-2">
                <Label
                    for="filter-tour"
                    class="text-[10.5px] font-semibold tracking-[0.09em] text-muted-foreground uppercase"
                >
                    {{ $t('Tour') }}
                </Label>
                <Select
                    :model-value="props.tourId"
                    @update:model-value="onTourChange"
                >
                    <SelectTrigger
                        id="filter-tour"
                        class="w-[190px] rounded-full"
                    >
                        <SelectValue :placeholder="$t('Todos')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem :value="props.allToursValue">
                                {{ $t('Todos') }}
                            </SelectItem>
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
            </div>

            <div class="flex items-center gap-2">
                <Label
                    for="filter-direction"
                    class="text-[10.5px] font-semibold tracking-[0.09em] text-muted-foreground uppercase"
                >
                    {{ $t('Orden') }}
                </Label>
                <Select
                    :model-value="props.direction"
                    @update:model-value="onDirectionChange"
                >
                    <SelectTrigger
                        id="filter-direction"
                        class="w-[160px] rounded-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="asc">
                                {{ $t('Más próxima') }}
                            </SelectItem>
                            <SelectItem value="desc">
                                {{ $t('Más lejana') }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>
        </template>
    </FilterBar>
</template>
