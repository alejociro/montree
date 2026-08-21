<script setup lang="ts">
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Tarjeta de cifra del panel: etiqueta mono, número grande y una línea de
 * detalle.
 *
 * WHY: el listado (`TourKpiGrid`) y el detalle del tour pintan la misma
 * tarjeta; tenerla dos veces garantizaba que se separaran al primer retoque.
 *
 * `alert` va por los tokens semánticos fijos (`--brand-drop`), no por
 * `--primary` (D5): un saldo pendiente es un estado, no la marca de la agencia.
 */
type Props = {
    label: string;
    /** Ya formateado por quien lo pasa: la tarjeta no sabe de monedas. */
    value: string;
    detail?: string | null;
    alert?: boolean;
    /** Mientras la cifra viaja: esqueleto en vez de un cero prestado. */
    loading?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    detail: null,
    alert: false,
    loading: false,
});
</script>

<template>
    <div
        class="rounded-xl border p-4 md:px-[18px]"
        :class="
            props.alert
                ? 'border-brand-drop/20 bg-brand-drop-50'
                : 'border-border bg-card'
        "
    >
        <MonoLabel>{{ props.label }}</MonoLabel>

        <Skeleton v-if="props.loading" class="mt-2 h-7 w-24" />

        <div v-else class="mt-1.5 flex items-center gap-2">
            <p
                class="text-2xl font-semibold tracking-tight tabular-nums"
                :class="props.alert ? 'text-brand-drop' : 'text-foreground'"
            >
                {{ props.value }}
            </p>
            <slot name="suffix" />
        </div>

        <Skeleton v-if="props.loading" class="mt-2 h-3.5 w-32" />
        <p v-else-if="props.detail" class="mt-1 text-xs text-muted-foreground">
            {{ props.detail }}
        </p>
    </div>
</template>
