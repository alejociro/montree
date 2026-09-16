<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/atoms/charts/BarChart.vue';
import ChartLegend from '@/components/atoms/charts/ChartLegend.vue';
import { chartColor } from '@/lib/chart';
import { formatCurrency } from '@/lib/format';
import type { PlatformCharts } from '@/types';

const props = defineProps<{
    months: PlatformCharts['revenue_per_tenant']['months'];
    series: PlatformCharts['revenue_per_tenant']['series'];
    currency: string;
}>();

const chartSeries = computed(() =>
    props.series.map((serie) => ({
        name: serie.tenant,
        values: serie.values.map((value) => Number(value)),
    })),
);

const legend = computed(() =>
    props.series.map((serie, index) => ({
        name: serie.tenant,
        color: chartColor(index),
    })),
);
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
        <header class="mb-4 space-y-2">
            <h2 class="text-base font-semibold text-foreground">
                {{ $t('Ingresos por agencia y mes') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{
                    $t(
                        'Pagos completados de los últimos 6 meses, las 8 agencias que más facturan.',
                    )
                }}
            </p>
            <ChartLegend v-if="legend.length > 0" :items="legend" />
        </header>

        <p
            v-if="chartSeries.length === 0"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            {{ $t('Todavía no hay pagos completados en el período.') }}
        </p>

        <BarChart
            v-else
            stacked
            :labels="months"
            :series="chartSeries"
            :caption="$t('Ingresos por agencia y mes')"
            :value-label="$t('Agencia')"
            :format-value="(value: number) => formatCurrency(value, currency)"
        />
    </section>
</template>
