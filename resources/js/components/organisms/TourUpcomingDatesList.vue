<script setup lang="ts">
import {
    Building2,
    CalendarClock,
    MapPin,
    Truck,
    UserRound,
} from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { index as datesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateController';
import OccupancyBar from '@/components/molecules/OccupancyBar.vue';
import TourDateStatusBadge from '@/components/molecules/TourDateStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDateAdmin } from '@/types/logistics';

type Props = {
    tourId: number;
    currency: string;
    /** Sin `bookings.view` no se ofrece el atajo a la planilla (F018). */
    canViewPassengers?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    canViewPassengers: false,
});

const emit = defineEmits<{
    /**
     * «Ver pasajeros» de una salida. La planilla no acepta filtro inicial, así
     * que la página solo abre la pestaña: la salida se elige en su selector.
     */
    (e: 'view-passengers', dateId: number): void;
}>();

const dates = ref<TourDateAdmin[]>([]);
const loading = ref(true);
const loadError = ref(false);

async function loadDates(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetch(
            datesIndex(props.tourId, { query: { scope: 'upcoming' } }).url,
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const json = (await response.json()) as { data: TourDateAdmin[] };
        dates.value = json.data;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(loadDates);
</script>

<template>
    <div>
        <div v-if="loading" class="space-y-3">
            <div
                v-for="n in 3"
                :key="n"
                class="h-20 animate-pulse rounded-xl bg-muted"
            />
        </div>

        <div
            v-else-if="loadError"
            class="rounded-xl border border-destructive/40 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">
                {{ $t('No se pudieron cargar las salidas.') }}
            </p>
            <Button variant="outline" size="sm" class="mt-3" @click="loadDates">
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <div
            v-else-if="dates.length === 0"
            class="rounded-xl border border-dashed border-border p-8 text-center"
        >
            <CalendarClock class="mx-auto size-8 text-muted-foreground/40" />
            <p class="mt-3 font-medium text-foreground">
                {{ $t('Sin salidas próximas') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ $t('Programa una salida desde la edición del producto.') }}
            </p>
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="date in dates"
                :key="date.id"
                class="rounded-xl border border-border bg-background p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-foreground capitalize">
                                {{ formatTourDate(date.starts_at) }}
                            </p>
                            <TourDateStatusBadge :status="date.status" />
                        </div>

                        <OccupancyBar
                            class="max-w-xs"
                            size="sm"
                            :occupied="date.booked_count"
                            :capacity="date.capacity"
                            :label="$t('Cupos vendidos')"
                        />

                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span
                                v-if="date.guide"
                                class="flex items-center gap-1"
                            >
                                <UserRound class="size-3.5" />
                                {{ date.guide.name }}
                            </span>
                            <span
                                v-if="date.route"
                                class="flex items-center gap-1"
                            >
                                <MapPin class="size-3.5" />
                                {{ date.route.name }}
                            </span>
                            <span
                                v-if="date.provider"
                                class="flex items-center gap-1"
                            >
                                <Truck class="size-3.5" />
                                {{ date.provider.name }}
                            </span>
                            <span
                                v-if="date.hotels.length > 0"
                                class="flex items-center gap-1"
                            >
                                <Building2 class="size-3.5" />
                                {{
                                    date.hotels
                                        .map((hotel) => hotel.name)
                                        .join(', ')
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <span
                            class="text-sm font-semibold text-foreground tabular-nums"
                        >
                            {{
                                formatCurrency(
                                    date.effective_price,
                                    props.currency,
                                )
                            }}
                        </span>
                        <Button
                            v-if="props.canViewPassengers"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="emit('view-passengers', date.id)"
                        >
                            {{ $t('Ver pasajeros') }}
                        </Button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
