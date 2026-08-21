<script setup lang="ts">
/**
 * Pestañas del tour (edición y detalle): subrayado en `--primary`, contador
 * opcional a la derecha del rótulo.
 *
 * Es presentación pura y sin genéricos: el `id` es un `string` y la página lo
 * vuelve a estrechar a su unión de pestañas al recibirlo.
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
</script>

<template>
    <nav
        class="flex gap-1 overflow-x-auto border-b border-border"
        role="tablist"
        :aria-label="props.label"
    >
        <button
            v-for="tab in props.tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="props.modelValue === tab.id"
            class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="
                props.modelValue === tab.id
                    ? 'border-primary text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            @click="emit('update:modelValue', tab.id)"
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
