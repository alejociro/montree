<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { PaymentStatus } from '@/types/enums.generated';

/**
 * Estado de una transacción.
 *
 * WHY no reusa `PaymentStatusChip`: ese chip habla del saldo de un pasajero
 * (`paid` / `due`), no del estado de una transacción (`pending`…`refunded`).
 * Son dos dominios distintos con los mismos colores, no el mismo componente.
 *
 * La etiqueta llega del backend (`PaymentStatus::label()`): el front no
 * mantiene su propia tabla de nombres de estado.
 */
type Props = {
    status: PaymentStatus;
    label: string;
    size?: 'sm' | 'md';
};

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const tones: Record<PaymentStatus, string> = {
    pending: 'bg-brand-warn-50 text-brand-warn border-brand-warn/25',
    processing: 'bg-brand-warn-50 text-brand-warn border-brand-warn/25',
    completed:
        'bg-brand-green-50 text-brand-green-600 border-brand-green-600/25',
    failed: 'bg-brand-drop-50 text-brand-drop border-brand-drop/25',
    refunded: 'bg-muted text-muted-foreground border-border',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center gap-1.5 rounded-full border font-medium whitespace-nowrap',
        props.size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs',
        tones[props.status],
    ),
);
</script>

<template>
    <span :class="classes">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ props.label }}
    </span>
</template>
