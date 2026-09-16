<script setup lang="ts">
import { Building2, DollarSign, ShoppingBag, Users } from 'lucide-vue-next';
import PlatformStatCard from '@/components/molecules/PlatformStatCard.vue';
import { formatCurrency, formatNumber } from '@/lib/format';
import type { PlatformMetricsGrowth, PlatformMetricsTotals } from '@/types';

defineProps<{
    totals: PlatformMetricsTotals;
    growth: PlatformMetricsGrowth;
    currency: string;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <PlatformStatCard
            :title="$t('Agencias activas')"
            :value="formatNumber(totals.active_tenants)"
            :description="
                $t('de :total totales', { total: formatNumber(totals.tenants) })
            "
            :icon="Building2"
        />
        <PlatformStatCard
            :title="$t('Usuarios')"
            :value="formatNumber(totals.users)"
            :description="$t('Cuentas registradas')"
            :icon="Users"
        />
        <PlatformStatCard
            :title="$t('Reservas del mes')"
            :value="formatNumber(totals.bookings_this_month)"
            :description="$t('vs. mes anterior')"
            :icon="ShoppingBag"
            :trend="growth.bookings_growth_pct"
        />
        <PlatformStatCard
            :title="$t('Ganancias del mes')"
            :value="formatCurrency(totals.earnings_this_month, currency)"
            :description="
                $t('Sobre :amount facturados', {
                    amount: formatCurrency(totals.revenue_this_month, currency),
                })
            "
            :icon="DollarSign"
        />
    </div>
</template>
