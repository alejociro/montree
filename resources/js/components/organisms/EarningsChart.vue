<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/atoms/charts/BarChart.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { PlatformCharts } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    series: PlatformCharts['earnings_per_month']['series'];
}>();

/** Una gráfica por moneda: los cargos no se convierten ni se suman entre sí. */
const charts = computed(() =>
    props.series.map((entry) => ({
        currency: entry.currency,
        total: entry.total,
        labels: entry.points.map((point) => point.label),
        series: [
            {
                name: t('Ganancias'),
                values: entry.points.map((point) => Number(point.value)),
            },
        ],
    })),
);
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
        <h2 class="text-base font-semibold text-foreground">
            {{ $t('Ganancias de la plataforma por mes') }}
        </h2>

        <p
            v-if="charts.length === 0"
            class="py-12 text-center text-sm text-muted-foreground"
        >
            {{ $t('Todavía no hay cargos en el período.') }}
        </p>

        <div v-for="chart in charts" :key="chart.currency" class="mt-4">
            <header class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-medium text-foreground">
                    {{ chart.currency }}
                </h3>
                <p class="text-xs text-muted-foreground">
                    {{
                        $t('Acumulado: :total', {
                            total: formatCurrency(chart.total, chart.currency),
                        })
                    }}
                </p>
            </header>

            <BarChart
                :labels="chart.labels"
                :series="chart.series"
                :caption="
                    $t('Ganancias de la plataforma por mes en :currency', {
                        currency: chart.currency,
                    })
                "
                :value-label="$t('Mes')"
                :format-value="
                    (value: number) => formatCurrency(value, chart.currency)
                "
            />
        </div>
    </section>
</template>
