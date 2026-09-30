<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import EarningsChart from '@/components/organisms/EarningsChart.vue';
import PlatformStats from '@/components/organisms/PlatformStats.vue';
import RevenueByTenantChart from '@/components/organisms/RevenueByTenantChart.vue';
import TenantsPerMonthChart from '@/components/organisms/TenantsPerMonthChart.vue';
import type {
    PlatformCharts,
    PlatformMetricsGrowth,
    PlatformMetricsTotals,
} from '@/types';

defineProps<{
    totals: PlatformMetricsTotals;
    growth: PlatformMetricsGrowth;
    charts: PlatformCharts;
}>();
</script>

<template>
    <Head :title="$t('Super admin · Dashboard')" />

    <div class="space-y-8 px-4 py-6 md:px-8">
        <Heading
            :title="$t('Panel de plataforma')"
            :description="
                $t(
                    'Métricas agregadas de todas las agencias y de la plataforma MONTREE.',
                )
            "
        />

        <PlatformStats :totals="totals" :growth="growth" />

        <TenantsPerMonthChart
            :points="charts.tenants_per_month.points"
            :average="charts.tenants_per_month.average"
        />

        <RevenueByTenantChart
            :months="charts.revenue_per_tenant.months"
            :series="charts.revenue_per_tenant.series"
        />

        <EarningsChart :series="charts.earnings_per_month.series" />
    </div>
</template>
