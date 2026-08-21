<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { cn } from '@/lib/utils';
import type { PassengerPaymentStatus } from '@/types/passenger';

const { t } = useTranslations();

type Props = {
    status: PassengerPaymentStatus;
    size?: 'sm' | 'md';
};

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const labels: Record<PassengerPaymentStatus, string> = {
    paid: t('Pagado'),
    due: t('Saldo pendiente'),
};

/**
 * WHY: verde y terracota FIJOS, no `--primary` (D4/D5). «Pagado» y «Saldo
 * pendiente» son estados, no marca: colgados del color del tenant, una agencia
 * con el principal en rojo mostraría «Pagado» en rojo.
 */
const tones: Record<PassengerPaymentStatus, string> = {
    paid: 'bg-brand-green-50 text-brand-green-600 border-brand-green-600/25',
    due: 'bg-brand-drop-50 text-brand-drop border-brand-drop/25',
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
        {{ labels[props.status] }}
    </span>
</template>
