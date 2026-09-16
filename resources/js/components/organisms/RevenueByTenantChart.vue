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
}>();

/**
 * Una gráfica por moneda. Apilar agencias que facturan en monedas distintas
 * dibujaría una torre cuya altura no significa nada.
 */
const groups = computed(() => {
    const byCurrency = new Map<
        string,
        PlatformCharts['revenue_per_tenant']['series']
    >();

    for (const serie of props.series) {
        byCurrency.set(serie.currency, [
            ...(byCurrency.get(serie.currency) ?? []),
            serie,
        ]);
    }

    return [...byCurrency.entries()].map(([currency, series]) => ({
        currency,
        chartSeries: series.map((serie) => ({
            name: serie.tenant,
            values: serie.values.map((value) => Number(value)),
        })),
        legend: series.map((serie, index) => ({
            name: serie.tenant,
            color: chartColor(index),
        })),
    }));
});
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
        </header>

        <p
            v-if="groups.length === 0"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            {{ $t('Todavía no hay pagos completados en el período.') }}
        </p>

        <div v-for="group in groups" :key="group.currency" class="mt-4">
            <div class="mb-2 flex flex-wrap items-baseline gap-3">
                <h3 class="text-sm font-medium text-foreground">
                    {{ group.currency }}
                </h3>
                <ChartLegend :items="group.legend" />
            </div>

            <BarChart
                stacked
                :labels="months"
                :series="group.chartSeries"
                :caption="
                    $t('Ingresos por agencia y mes en :currency', {
                        currency: group.currency,
                    })
                "
                :value-label="$t('Agencia')"
                :format-value="
                    (value: number) => formatCurrency(value, group.currency)
                "
            />
        </div>
    </section>
</template>
