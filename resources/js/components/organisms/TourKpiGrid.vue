<script setup lang="ts">
import { computed } from 'vue';
import KpiCard from '@/components/atoms/KpiCard.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatDate, formatNumber } from '@/lib/format';
import type { TourIndexStats } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    stats: TourIndexStats;
};

const props = defineProps<Props>();

type Kpi = {
    key: string;
    label: string;
    value: string;
    detail: string;
    /**
     * WHY: el saldo pendiente es un estado, no marca — va por los tokens
     * semánticos fijos (`--brand-drop`), no por `--primary` (D5).
     */
    alert?: boolean;
};

const kpis = computed<Kpi[]>(() => {
    const { tours, upcoming_departures, occupancy, pending_balance } =
        props.stats;

    return [
        {
            key: 'active',
            label: t('Tours activos'),
            value: formatNumber(tours.active),
            detail: t(':draft en borrador · :archived archivados', {
                draft: formatNumber(tours.draft),
                archived: formatNumber(tours.archived),
            }),
        },
        {
            key: 'departures',
            label: t('Salidas próximas 30 días'),
            value: formatNumber(upcoming_departures.count),
            detail:
                upcoming_departures.next_starts_at === null
                    ? t('Sin salidas programadas')
                    : t('Próxima: :date', {
                          date: formatDate(upcoming_departures.next_starts_at),
                      }),
        },
        {
            key: 'occupancy',
            label: t('Ocupación media'),
            value: t(':percent %', {
                percent: formatNumber(occupancy.rate),
            }),
            detail: t(':booked de :capacity cupos', {
                booked: formatNumber(occupancy.booked_seats),
                capacity: formatNumber(occupancy.total_capacity),
            }),
        },
        // WHY: sin `bookings.view` el backend no manda el saldo. Se cae el KPI
        // entero en vez de pintar un cero que se leería como «no se debe nada».
        ...(pending_balance === undefined
            ? []
            : [
                  {
                      key: 'due',
                      label: t('Pasajeros con saldo'),
                      value: formatNumber(pending_balance.passengers),
                      detail: t(':amount por cobrar', {
                          amount: formatCurrency(
                              pending_balance.amount,
                              pending_balance.currency,
                          ),
                      }),
                      alert: true,
                  },
              ]),
    ];
});
</script>

<template>
    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
            v-for="kpi in kpis"
            :key="kpi.key"
            :label="kpi.label"
            :value="kpi.value"
            :detail="kpi.detail"
            :alert="kpi.alert"
        />
    </div>
</template>
