<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatTourDate } from '@/lib/format';
import type { TourDetailDate } from '@/types/tour-detail';

const props = defineProps<{ date: TourDetailDate; currency: string }>();

const formattedDate = computed(() =>
    formatTourDate(props.date.starts_at, { withWeekday: true, withTime: true }),
);

const formattedPrice = computed(() =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: props.currency,
        maximumFractionDigits: 0,
    }).format(Number(props.date.effective_price)),
);

const disabled = computed(
    () => props.date.is_full || props.date.status !== 'open',
);
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-lg border p-4 transition"
        :class="
            disabled ? 'opacity-60' : 'hover:border-primary hover:shadow-sm'
        "
    >
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="font-medium capitalize">{{ formattedDate }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ date.available_seats }} cupos · {{ formattedPrice }}
                </p>
            </div>
            <span
                v-if="date.is_full"
                class="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive"
            >
                Agotado
            </span>
        </div>
        <Button as-child :disabled="disabled" size="sm">
            <Link
                :href="`/booking/new?tour_date_id=${date.id}`"
                v-if="!disabled"
            >
                Reservar
            </Link>
            <span v-else>No disponible</span>
        </Button>
    </div>
</template>
