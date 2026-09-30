<script setup lang="ts">
import {
    Building2,
    CalendarClock,
    MapPin,
    Truck,
    UserRound,
} from 'lucide-vue-next';
import OccupancyBar from '@/components/molecules/OccupancyBar.vue';
import TourDateStatusBadge from '@/components/molecules/TourDateStatusBadge.vue';
import { useModules } from '@/composables/useModules';
import { useTenantCurrency } from '@/composables/useTenant';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDateAdmin } from '@/types/logistics';

type Props = {
    dates: TourDateAdmin[];
    /** Sin `bookings.view` no se ofrece el atajo a la planilla (F018). */
    canViewPassengers?: boolean;
};

const currency = useTenantCurrency();
// WHY (T3): con el módulo apagado el listado no muestra proveedor ni hotel
// aunque la salida los tenga guardados de antes de apagarlo.
const { isModuleEnabled } = useModules();
const logisticsEnabled = isModuleEnabled('logistics');

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
</script>

<template>
    <div>
        <div
            v-if="dates.length === 0"
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
                            <!-- T7: algún bloque de "Contenido de la salida"
                                 tiene valor propio, distinto del producto. -->
                            <span
                                v-if="date.is_customized"
                                class="inline-flex items-center rounded-full bg-secondary-soft px-2 py-0.5 text-[11px] font-semibold text-secondary-readable"
                            >
                                {{ $t('Personalizada') }}
                            </span>
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
                                v-else
                                class="flex items-center gap-1 font-medium text-brand-warn"
                            >
                                <UserRound class="size-3.5" />
                                {{ $t('Guía por asignar') }}
                            </span>
                            <span
                                v-if="date.route"
                                class="flex items-center gap-1"
                            >
                                <MapPin class="size-3.5" />
                                {{ date.route.name }}
                            </span>
                            <span
                                v-if="logisticsEnabled && date.provider"
                                class="flex items-center gap-1"
                            >
                                <Truck class="size-3.5" />
                                {{ date.provider.name }}
                            </span>
                            <span
                                v-if="
                                    logisticsEnabled && date.hotels.length > 0
                                "
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
                            {{ formatCurrency(date.effective_price, currency) }}
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
