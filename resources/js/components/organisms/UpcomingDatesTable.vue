<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import type { DashboardUpcomingDate } from '@/types/dashboard';

type Props = {
    dates: DashboardUpcomingDate[];
};

const props = defineProps<Props>();

const sorted = computed(() =>
    [...props.dates].sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
);

function occupancyColor(pct: number | null): string {
    if (pct === null) {
        return 'bg-muted-foreground/40';
    }

    if (pct >= 80) {
        return 'bg-emerald-500';
    }

    if (pct >= 50) {
        return 'bg-amber-500';
    }

    return 'bg-sky-500';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Próximas fechas</CardTitle>
            <CardDescription>
                Salidas programadas en los próximos 7 días.
            </CardDescription>
        </CardHeader>
        <CardContent class="px-0">
            <div
                v-if="sorted.length === 0"
                class="px-6 py-8 text-center text-sm text-muted-foreground"
            >
                No hay fechas programadas en los próximos 7 días.
            </div>
            <ul v-else class="divide-y divide-border">
                <li
                    v-for="date in sorted"
                    :key="date.id"
                    class="grid gap-3 px-6 py-3 md:grid-cols-[minmax(0,1fr)_140px_120px] md:items-center"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ date.tour_name ?? 'Tour eliminado' }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ formatDateTime(date.starts_at) }}
                            <span v-if="date.guide_name">
                                · {{ date.guide_name }}</span
                            >
                        </p>
                    </div>

                    <div class="text-xs text-muted-foreground">
                        {{ date.capacity_booked }} /
                        {{ date.capacity_total }} asientos
                    </div>

                    <div class="flex items-center gap-2">
                        <div
                            class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="occupancyColor(date.occupancy_pct)"
                                :style="{
                                    width: `${Math.min(date.occupancy_pct ?? 0, 100)}%`,
                                }"
                            />
                        </div>
                        <span class="w-10 text-right text-xs font-medium">
                            {{ date.occupancy_pct ?? 0 }}%
                        </span>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
