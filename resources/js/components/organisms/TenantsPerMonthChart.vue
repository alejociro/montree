<script setup lang="ts">
import { computed } from 'vue';
import LineChart from '@/components/atoms/charts/LineChart.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import type { MonthPoint } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    points: MonthPoint[];
    average: number;
}>();

const labels = computed(() => props.points.map((point) => point.label));

const series = computed(() => [
    {
        name: t('Agencias registradas'),
        values: props.points.map((point) => Number(point.value)),
    },
]);
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
        <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-base font-semibold text-foreground">
                {{ $t('Agencias registradas por mes') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{
                    $t('Promedio mensual: :average', {
                        average: formatNumber(average),
                    })
                }}
            </p>
        </header>

        <LineChart
            :labels="labels"
            :series="series"
            :average="average"
            :average-label="$t('Promedio')"
            :caption="$t('Agencias registradas por mes')"
            :value-label="$t('Mes')"
            :format-value="(value: number) => formatNumber(Math.round(value))"
        />
    </section>
</template>
