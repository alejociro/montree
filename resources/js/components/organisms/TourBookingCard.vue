<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarOff, MapPinned, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type { TourDetail, TourDetailDate } from '@/types/tour-detail';

const { t } = useTranslations();

const props = defineProps<{
    tour: TourDetail;
    dates: TourDetailDate[];
    selectedDateId: number | null;
    /** Índice de la parada de recogida; null cuando el tour no la define. */
    pickupStopIndex: number | null;
}>();

const emit = defineEmits<{
    (e: 'update:selectedDateId', value: number | null): void;
    (e: 'show-pickup'): void;
}>();

const selectedDate = computed<TourDetailDate | null>(
    () => props.dates.find((date) => date.id === props.selectedDateId) ?? null,
);

const price = computed(() =>
    formatCurrency(
        selectedDate.value?.effective_price ?? props.tour.base_price,
        props.tour.currency,
    ),
);

const bookingUrl = computed(() =>
    selectedDate.value === null
        ? null
        : `/booking/new?tour_date_id=${selectedDate.value.id}`,
);

function dateOptionLabel(date: TourDetailDate): string {
    return t(':date · :seats cupos · :price', {
        date: formatTourDate(date.starts_at, {
            withWeekday: true,
            withTime: true,
        }),
        seats: date.available_seats,
        price: formatCurrency(date.effective_price, props.tour.currency),
    });
}
</script>

<template>
    <aside class="flex flex-col gap-3.5 lg:sticky lg:top-[78px]">
        <div
            class="rounded-2xl border border-border bg-card p-5 shadow-[0_14px_40px_-28px_rgba(20,48,31,0.5)]"
        >
            <p class="flex items-baseline gap-2">
                <span class="text-3xl font-semibold tracking-tight">{{
                    price
                }}</span>
                <span class="text-[13px] text-muted-foreground"
                    >/ {{ $t('persona') }}</span
                >
            </p>

            <template v-if="dates.length > 0">
                <div
                    class="mt-4 rounded-[10px] border border-border px-2.5 py-2"
                >
                    <label
                        for="tour-date-select"
                        class="block text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                    >
                        {{ $t('Fecha de salida') }}
                    </label>
                    <select
                        id="tour-date-select"
                        class="w-full bg-transparent text-sm capitalize outline-none"
                        :value="selectedDateId"
                        @change="
                            emit(
                                'update:selectedDateId',
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ) || null,
                            )
                        "
                    >
                        <option :value="null" disabled>
                            {{ $t('Seleccionar fecha') }}
                        </option>
                        <option
                            v-for="date in dates"
                            :key="date.id"
                            :value="date.id"
                        >
                            {{ dateOptionLabel(date) }}
                        </option>
                    </select>
                </div>

                <p
                    v-if="selectedDate"
                    class="mt-2.5 flex items-center gap-1.5 text-[13px] text-muted-foreground"
                >
                    <Users class="size-4 text-primary" />
                    {{
                        $tc(
                            ':count cupo disponible|:count cupos disponibles',
                            selectedDate.available_seats,
                        )
                    }}
                </p>

                <Link
                    v-if="bookingUrl"
                    :href="bookingUrl"
                    class="mt-3.5 block rounded-full bg-primary px-4 py-3.5 text-center text-[15px] font-semibold text-primary-foreground transition hover:bg-brand-ink"
                >
                    {{ $t('Reservar ahora') }}
                </Link>
                <button
                    v-else
                    type="button"
                    disabled
                    class="mt-3.5 w-full rounded-full bg-primary/50 px-4 py-3.5 text-[15px] font-semibold text-primary-foreground"
                >
                    {{ $t('Reservar ahora') }}
                </button>
            </template>

            <div
                v-else
                class="mt-4 rounded-xl border border-dashed border-border px-4 py-6 text-center"
            >
                <CalendarOff class="mx-auto size-8 text-muted-foreground/40" />
                <p class="mt-3 font-medium">
                    {{ $t('Sin fechas disponibles') }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        tour.future_dates.length === 0
                            ? $t(
                                  'Todavía no hay salidas programadas para esta experiencia. Vuelve pronto para reservar.',
                              )
                            : $t(
                                  'Por ahora no quedan cupos abiertos. Vuelve pronto para nuevas salidas.',
                              )
                    }}
                </p>
            </div>

            <button
                v-if="pickupStopIndex !== null"
                type="button"
                class="mt-2 flex w-full items-center justify-center gap-2 rounded-full border border-border px-4 py-3 text-sm font-semibold text-primary transition hover:bg-brand-green-100"
                @click="emit('show-pickup')"
            >
                <MapPinned class="size-4" />
                {{ $t('Ver punto de recogida en el mapa') }}
            </button>
        </div>

        <slot name="logistics" />
    </aside>
</template>
