<script setup lang="ts">
import { nextTick } from 'vue';

/**
 * Pestañas con conteo en forma de píldora: la bandeja activa se rellena con el
 * color principal y cada una lleva cuántos registros contiene.
 *
 * WHY: el sistema de diseño pide este control —y no una rejilla de selects—
 * para cortar un listado por estado. El conteo va al lado del rótulo porque la
 * pregunta real del operador es «¿cuánto trabajo hay en esta bandeja?».
 *
 * Patrón ARIA de tabs: flechas ← →, Home/End y activación automática. El panel
 * es la tabla que pinta la página; ella pone `role="tabpanel"`.
 */
export type CountTab = {
    id: string;
    label: string;
    /** Se omite mientras el conteo no haya llegado: un `0` prestado miente. */
    count?: number | null;
};

type Props = {
    tabs: CountTab[];
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
    <div
        class="flex flex-wrap items-center gap-1"
        role="tablist"
        :aria-label="props.label"
    >
        <button
            v-for="(tab, index) in props.tabs"
            :key="tab.id"
            :ref="(el) => registerButton(tab.id, el)"
            type="button"
            role="tab"
            :aria-selected="props.modelValue === tab.id"
            :tabindex="props.modelValue === tab.id ? 0 : -1"
            class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 text-[13px] font-medium whitespace-nowrap transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                props.modelValue === tab.id
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-primary-soft hover:text-foreground'
            "
            @click="emit('update:modelValue', tab.id)"
            @keydown="onKeydown($event, index)"
        >
            {{ tab.label }}
            <span
                v-if="tab.count !== undefined && tab.count !== null"
                class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular-nums"
                :class="
                    props.modelValue === tab.id
                        ? 'bg-white/20'
                        : 'bg-muted text-muted-foreground'
                "
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
