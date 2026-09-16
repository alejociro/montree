<script setup lang="ts">
import { computed } from 'vue';
import BarChart from '@/components/atoms/charts/BarChart.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency } from '@/lib/format';
import type { MonthPoint } from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    points: MonthPoint[];
    total: string;
    currency: string;
}>();

const labels = computed(() => props.points.map((point) => point.label));

const series = computed(() => [
    {
        name: t('Ganancias'),
        values: props.points.map((point) => Number(point.value)),
    },
]);
</script>

<template>
    <section class="rounded-lg border border-border bg-card p-6 shadow-sm">
        <header class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-base font-semibold text-foreground">
                {{ $t('Ganancias de la plataforma por mes') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{
                    $t('Acumulado: :total', {
                        total: formatCurrency(total, currency),
                    })
                }}
            </p>
        </header>

        <BarChart
            :labels="labels"
            :series="series"
            :caption="$t('Ganancias de la plataforma por mes')"
            :value-label="$t('Mes')"
            :format-value="(value: number) => formatCurrency(value, currency)"
        />
    </section>
</template>
