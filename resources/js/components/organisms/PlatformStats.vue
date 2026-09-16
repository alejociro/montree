<script setup lang="ts">
import { Building2, DollarSign, ShoppingBag, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import PlatformStatCard from '@/components/molecules/PlatformStatCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatNumber } from '@/lib/format';
import type {
    CurrencyAmount,
    PlatformMetricsGrowth,
    PlatformMetricsTotals,
} from '@/types';

const { t } = useTranslations();

const props = defineProps<{
    totals: PlatformMetricsTotals;
    growth: PlatformMetricsGrowth;
}>();

/**
 * Los agregados se presentan por moneda, nunca sumados entre monedas: el cargo
 * de una agencia en pesos y el de otra en dólares no comparten unidad.
 */
function byCurrency(amounts: CurrencyAmount[], empty: string): string {
    if (amounts.length === 0) {
        return empty;
    }

    return amounts
        .map((entry) => formatCurrency(entry.amount, entry.currency))
        .join(' · ');
}

const earnings = computed(() =>
    byCurrency(props.totals.earnings_this_month, t('Sin cargos')),
);

const revenue = computed(() =>
    byCurrency(props.totals.revenue_this_month, t('Sin pagos')),
);
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
            :value="earnings"
            :description="$t('Sobre :amount facturados', { amount: revenue })"
            :icon="DollarSign"
        />
    </div>
</template>
