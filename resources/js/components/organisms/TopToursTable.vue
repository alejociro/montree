<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatCurrency, formatNumber } from '@/lib/format';
import type { DashboardTopTour } from '@/types/dashboard';

type Props = {
    tours: DashboardTopTour[];
    currency: string;
};

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Tours más reservados</CardTitle>
            <CardDescription>
                Los 5 tours con más reservas en el periodo.
            </CardDescription>
        </CardHeader>
        <CardContent class="px-0">
            <div
                v-if="tours.length === 0"
                class="px-6 py-8 text-center text-sm text-muted-foreground"
            >
                No hay reservas en este periodo.
            </div>
            <ul v-else class="divide-y divide-border">
                <li
                    v-for="tour in tours"
                    :key="tour.id"
                    class="flex items-center gap-4 px-6 py-3"
                >
                    <div
                        class="flex size-12 flex-none items-center justify-center overflow-hidden rounded-md bg-muted"
                    >
                        <img
                            v-if="tour.cover_image_url"
                            :src="tour.cover_image_url"
                            :alt="tour.name"
                            class="size-full object-cover"
                        />
                        <span
                            v-else
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            {{ tour.name.slice(0, 2).toUpperCase() }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ tour.name }}
                        </p>
                        <p
                            class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Star class="size-3" />
                            {{ tour.rating_average }} ·
                            {{ formatNumber(tour.bookings_count) }} reservas
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-sm font-semibold">
                            {{ formatCurrency(tour.revenue, currency) }}
                        </p>
                        <p class="text-xs text-muted-foreground">ingresos</p>
                    </div>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
