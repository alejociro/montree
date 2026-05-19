<script setup lang="ts">
import { Building2, DollarSign, ShoppingBag, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import PlatformStatCard from '@/components/molecules/PlatformStatCard.vue';
import type { PlatformMetrics } from '@/types';

const props = defineProps<{
    metrics: PlatformMetrics | null;
}>();

const totals = computed(() => props.metrics?.totals);
const growth = computed(() => props.metrics?.growth);

function formatNumber(value: number | undefined): string {
    if (value === undefined) {
        return '—';
    }

    return value.toLocaleString('es-CO');
}

function formatCurrency(value: string | undefined): string {
    if (value === undefined) {
        return '—';
    }

    return Number(value).toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    });
}
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <PlatformStatCard
            title="Tenants activos"
            :value="formatNumber(totals?.active_tenants)"
            :description="`de ${formatNumber(totals?.tenants)} totales`"
            :icon="Building2"
        />
        <PlatformStatCard
            title="Usuarios"
            :value="formatNumber(totals?.users)"
            description="Cuentas registradas"
            :icon="Users"
        />
        <PlatformStatCard
            title="Bookings del mes"
            :value="formatNumber(totals?.bookings_this_month)"
            description="vs. mes anterior"
            :icon="ShoppingBag"
            :trend="growth?.bookings_growth_pct ?? null"
        />
        <PlatformStatCard
            title="Comisión plataforma"
            :value="formatCurrency(totals?.platform_commission_this_month)"
            :description="`Sobre ${formatCurrency(totals?.revenue_this_month)} facturados`"
            :icon="DollarSign"
        />
    </div>
</template>
