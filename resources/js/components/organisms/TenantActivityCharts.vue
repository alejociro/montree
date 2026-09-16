<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/atoms/charts/BarChart.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatNumber } from '@/lib/format';
import type { TenantMonthlySeries } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    monthly: TenantMonthlySeries;
    currency: string;
}>();

const bookingLabels = computed(() =>
    props.monthly.bookings.map((point) => point.label),
);

const bookingSeries = computed(() => [
    {
        name: t('Reservas'),
        values: props.monthly.bookings.map((point) => Number(point.value)),
    },
]);

const chargeLabels = computed(() =>
    props.monthly.charges.map((point) => point.label),
);

const chargeSeries = computed(() => [
    {
        name: t('Cargos'),
        values: props.monthly.charges.map((point) => Number(point.value)),
    },
]);
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-2">
        <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-foreground">
                {{ $t('Reservas por mes') }}
            </h2>
            <BarChart
                :labels="bookingLabels"
                :series="bookingSeries"
                :caption="$t('Reservas por mes')"
                :value-label="$t('Mes')"
                :format-value="
                    (value: number) => formatNumber(Math.round(value))
                "
            />
        </section>

        <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold text-foreground">
                {{ $t('Cargos de plataforma por mes') }}
            </h2>
            <BarChart
                :labels="chargeLabels"
                :series="chargeSeries"
                :caption="$t('Cargos de plataforma por mes')"
                :value-label="$t('Mes')"
                :format-value="
                    (value: number) => formatCurrency(value, currency)
                "
            />
        </section>
    </div>
</template>
