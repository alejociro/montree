<script setup lang="ts">
import { nextTick } from 'vue';
import { tourTabId, tourTabPanelId } from '@/lib/tour-tabs';

/**
 * Pestañas del tour (edición y detalle): subrayado en `--primary`, contador
 * opcional a la derecha del rótulo.
 *
 * Es presentación pura y sin genéricos: el `id` es un `string` y la página lo
 * vuelve a estrechar a su unión de pestañas al recibirlo.
 *
 * Implementa el patrón ARIA de tabs completo: `tablist`/`tab` con
 * `aria-controls` acá, `tabpanel` + `aria-labelledby` en la página, tabulador
 * móvil (solo la pestaña activa entra con Tab) y flechas ← → con Home/End.
 * La activación es automática —moverse con las flechas cambia de pestaña—
 * porque los paneles ya están montados y cambiar no cuesta una carga.
 */
export type TourTabItem = {
    id: string;
    label: string;
    /** Se oculta mientras el dato no haya llegado: un `0` prestado miente. */
    count?: number | null;
};

type Props = {
    tabs: TourTabItem[];
    modelValue: string;
    label: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const buttons = new Map<string, HTMLButtonElement>();

function registerButton(id: string, el: unknown): void {
    if (el instanceof HTMLButtonElement) {
        buttons.set(id, el);

        return;
    }

    buttons.delete(id);
}

/**
 * Mueve el foco y la selección a la pestaña de `index`, envolviendo por los
 * extremos como pide el patrón ARIA.
 */
function moveTo(index: number): void {
    const total = props.tabs.length;

    if (total === 0) {
        return;
    }

    const target = props.tabs[(index + total) % total];

    emit('update:modelValue', target.id);

    void nextTick(() => {
        buttons.get(target.id)?.focus();
    });
}

function onKeydown(event: KeyboardEvent, currentIndex: number): void {
    const moves: Record<string, number> = {
        ArrowRight: currentIndex + 1,
        ArrowLeft: currentIndex - 1,
        Home: 0,
        End: props.tabs.length - 1,
    };

    const next = moves[event.key];

    if (next === undefined) {
        return;
    }

    event.preventDefault();
    moveTo(next);
}
</script>

<template>
    <nav
        class="flex gap-1 overflow-x-auto border-b border-border"
        role="tablist"
        :aria-label="props.label"
    >
        <button
            v-for="(tab, index) in props.tabs"
            :id="tourTabId(tab.id)"
            :key="tab.id"
            :ref="(el) => registerButton(tab.id, el)"
            type="button"
            role="tab"
            :aria-selected="props.modelValue === tab.id"
            :aria-controls="tourTabPanelId(tab.id)"
            :tabindex="props.modelValue === tab.id ? 0 : -1"
            class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                props.modelValue === tab.id
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            @click="emit('update:modelValue', tab.id)"
            @keydown="onKeydown($event, index)"
        >
            {{ tab.label }}
            <span
                v-if="tab.count !== undefined && tab.count !== null"
                class="rounded-full bg-secondary px-1.5 py-0.5 text-[11px] font-semibold text-secondary-foreground tabular-nums"
            >
                {{ tab.count }}
            </span>
        </button>
    </nav>
</template>
