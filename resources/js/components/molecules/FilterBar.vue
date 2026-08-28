<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import CountTabs from '@/components/molecules/CountTabs.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';

/**
 * Barra de filtros del panel, en el orden que fija el sistema de diseño:
 * buscador ancho arriba con su contador de resultados y, bajo un separador,
 * las pestañas con conteo a la izquierda y los selects a la derecha.
 *
 * WHY: cada listado había resuelto sus filtros por su cuenta —rejillas de
 * cinco selects, buscadores de 180 px— y ninguno se parecía al siguiente. La
 * barra vive una sola vez para que Salidas, Logística, Equipo, Promociones y
 * Reseñas se filtren igual.
 */
type Props = {
    search: string;
    placeholder: string;
    /** «9 de 12»: cuántos registros pasan el filtro sobre el total. */
    resultLabel?: string | null;
    tabs?: CountTab[];
    activeTab?: string;
    tabsLabel?: string;
    searchId?: string;
};

const props = withDefaults(defineProps<Props>(), {
    resultLabel: null,
    tabs: () => [],
    activeTab: '',
    tabsLabel: '',
    searchId: 'filter-search',
});

const emit = defineEmits<{
    (e: 'update:search', value: string): void;
    (e: 'update:activeTab', value: string): void;
}>();
</script>

<template>
    <div class="rounded-2xl border border-border bg-card">
        <div class="p-3.5">
            <div
                class="flex h-11 items-center gap-2.5 rounded-[10px] border border-border bg-background px-3.5 focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/40"
            >
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <label :for="props.searchId" class="sr-only">
                    {{ props.placeholder }}
                </label>
                <input
                    :id="props.searchId"
                    type="search"
                    :value="props.search"
                    :placeholder="props.placeholder"
                    class="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    @input="
                        emit(
                            'update:search',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
                <span
                    v-if="props.resultLabel"
                    class="shrink-0 text-[11px] font-semibold tracking-[0.09em] text-muted-foreground uppercase tabular-nums"
                >
                    {{ props.resultLabel }}
                </span>
            </div>
        </div>

        <div
            v-if="props.tabs.length > 0 || $slots.selects"
            class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-line-2 px-3.5 py-3"
        >
            <CountTabs
                v-if="props.tabs.length > 0"
                :tabs="props.tabs"
                :model-value="props.activeTab"
                :label="props.tabsLabel || props.placeholder"
                @update:model-value="emit('update:activeTab', $event)"
            />
            <div class="flex flex-wrap items-center gap-2.5">
                <slot name="selects" />
            </div>
        </div>
    </div>
</template>
