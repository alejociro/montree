<script setup lang="ts">
import { Calendar, DollarSign, Star, TicketCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/components/molecules/StatCard.vue';
import { formatCurrency, formatNumber } from '@/lib/format';
import type {
    DashboardBookings,
    DashboardOccupancy,
    DashboardRating,
    DashboardRevenue,
} from '@/types/dashboard';

type Props = {
    revenue: DashboardRevenue;
    bookings: DashboardBookings;
    rating: DashboardRating;
    occupancy: DashboardOccupancy;
};

const props = defineProps<Props>();

const grossLabel = computed(() =>
    formatCurrency(props.revenue.gross, props.revenue.currency),
);

const netLabel = computed(() =>
    formatCurrency(props.revenue.net, props.revenue.currency),
);

const ratingLabel = computed(() => {
    const value = Number.parseFloat(props.rating.average);

    return Number.isFinite(value) ? value.toFixed(1) : '0.0';
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
            title="Ingresos brutos"
            :value="grossLabel"
            :icon="DollarSign"
            :trend="revenue.growth_pct"
            trend-label="vs periodo anterior"
        />
        <StatCard
            title="Ingresos netos"
            :value="netLabel"
            :icon="DollarSign"
            description="Después de reembolsos"
        />
        <StatCard
            title="Reservas"
            :value="formatNumber(bookings.total)"
            :icon="TicketCheck"
            :trend="bookings.growth_pct"
            trend-label="vs periodo anterior"
        />
        <StatCard
            title="Rating promedio"
            :value="ratingLabel"
            :icon="Star"
            :description="`${formatNumber(rating.count)} reseñas`"
        />
        <StatCard
            title="Ocupación próxima"
            :value="`${occupancy.occupancy_pct ?? 0}%`"
            :icon="Calendar"
            :description="`${formatNumber(occupancy.booked_seats)} / ${formatNumber(occupancy.total_capacity)} asientos`"
        />
    </div>
</template>
