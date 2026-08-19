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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDateAdmin, TourDateStatus } from '@/types/logistics';

const { t } = useTranslations();

type Props = {
    tourId: number;
    currency: string;
};

const props = defineProps<Props>();

const dates = ref<TourDateAdmin[]>([]);
const loading = ref(true);
const loadError = ref(false);

const statusMeta: Record<
    TourDateStatus,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    open: { label: t('Abierta'), variant: 'default' },
    full: { label: t('Completa'), variant: 'secondary' },
    closed: { label: t('Cerrada'), variant: 'outline' },
    cancelled: { label: t('Cancelada'), variant: 'destructive' },
};

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

function occupancyRate(date: TourDateAdmin): number {
    if (date.capacity <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((date.booked_count / date.capacity) * 100));
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
                            <Badge :variant="statusMeta[date.status].variant">
                                {{ statusMeta[date.status].label }}
                            </Badge>
                        </div>

                        <div class="flex items-center gap-3">
                            <div
                                class="h-1.5 w-28 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :style="{
                                        width: `${occupancyRate(date)}%`,
                                    }"
                                />
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ date.booked_count }}/{{ date.capacity }}
                                cupos
                            </span>
                        </div>

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

                    <span
                        class="shrink-0 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{
                            formatCurrency(date.effective_price, props.currency)
                        }}
                    </span>
                </div>
            </li>
        </ul>
    </div>
</template>
