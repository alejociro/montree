<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    edit as editPage,
    show as showPage,
} from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import OccupancyBar from '@/components/molecules/OccupancyBar.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { categoryLabel } from '@/lib/categories';
import { formatCurrency, formatDate } from '@/lib/format';
import type { TourSummary } from '@/types/tour';

const { t } = useTranslations();

type Props = {
    tour: TourSummary;
    /** Moneda del tenant, para los tours que no traen la suya. */
    fallbackCurrency?: string;
};

const props = withDefaults(defineProps<Props>(), { fallbackCurrency: 'USD' });

const showUrl = computed(() => showPage({ tour: props.tour.id }).url);
const editUrl = computed(() => editPage({ tour: props.tour.id }).url);

const subtitle = computed<string>(() => {
    const parts: string[] = [];

    if (props.tour.category !== null && props.tour.category !== undefined) {
        parts.push(categoryLabel(props.tour.category.name));
    }

    parts.push(t(':hours h', { hours: props.tour.duration_hours }));
    parts.push(t(':capacity cupos', { capacity: props.tour.default_capacity }));

    return parts.join(' · ');
});

const price = computed<string>(() =>
    formatCurrency(
        props.tour.base_price,
        props.tour.currency || props.fallbackCurrency,
    ),
);

/**
 * WHY: cifras operativas opcionales. `TourSummaryResource` todavía no las
 * emite; el bloque no se dibuja en vez de inventar ceros que se leerían como
 * «este tour no tiene pasajeros».
 */
const operations = computed(() => props.tour.operations ?? null);

const nextDeparture = computed<string>(() => {
    const value = operations.value?.next_departure_at ?? null;

    return value === null ? t('Sin salidas') : formatDate(value);
});
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-xl border border-border bg-card transition hover:border-primary/40 hover:shadow-[0_12px_34px_-26px_rgba(20,48,31,0.55)]"
    >
        <Link
            :href="showUrl"
            class="group relative block focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
        >
            <div class="aspect-[16/9] overflow-hidden bg-muted">
                <img
                    v-if="props.tour.cover_image_url"
                    :src="props.tour.cover_image_url"
                    :alt="props.tour.name"
                    class="size-full object-cover transition duration-300 group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex size-full items-center justify-center bg-brand-green-50"
                >
                    <MonoLabel>{{ $t('Sin portada') }}</MonoLabel>
                </div>
            </div>
            <TourStatusBadge
                :status="props.tour.status"
                class="absolute top-2.5 left-2.5 shadow-sm"
            />
        </Link>

        <div class="flex flex-1 flex-col gap-2.5 px-4 py-3.5">
            <div>
                <h3 class="text-[15px] leading-snug font-semibold">
                    <Link
                        :href="showUrl"
                        class="hover:underline focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                    >
                        {{ props.tour.name }}
                    </Link>
                </h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>

            <dl
                v-if="operations"
                class="grid grid-cols-2 gap-x-3.5 gap-y-2 text-xs text-muted-foreground"
            >
                <div>
                    <dt>{{ $t('Próxima salida') }}</dt>
                    <dd class="font-semibold text-foreground">
                        {{ nextDeparture }}
                    </dd>
                </div>
                <div>
                    <dt>{{ $t('Pasajeros') }}</dt>
                    <dd class="font-semibold text-foreground tabular-nums">
                        {{ operations.passengers_count }}
                    </dd>
                </div>
            </dl>

            <OccupancyBar
                v-if="operations"
                class="mt-auto"
                size="sm"
                :label="$t('Ocupación próxima salida')"
                :occupied="operations.occupancy.occupied"
                :capacity="operations.occupancy.capacity"
            />

            <p
                v-if="operations && operations.passengers_with_due > 0"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-drop"
            >
                <span class="size-1.5 rounded-full bg-current" />
                {{
                    $tc(
                        ':count pasajero con saldo pendiente|:count pasajeros con saldo pendiente',
                        operations.passengers_with_due,
                    )
                }}
            </p>
        </div>

        <div
            class="flex items-center justify-between gap-2.5 border-t border-brand-line-2 px-4 py-2.5"
        >
            <span class="text-[15px] leading-tight font-bold">
                {{ price }}
                <small
                    class="block text-[11.5px] font-medium text-muted-foreground"
                >
                    {{ $t('por persona') }}
                </small>
            </span>
            <span class="flex gap-1.5">
                <Link :href="showUrl">
                    <Button size="sm" variant="ghost">{{ $t('Ver') }}</Button>
                </Link>
                <Link :href="editUrl">
                    <Button size="sm" variant="outline">{{
                        $t('Editar')
                    }}</Button>
                </Link>
            </span>
        </div>
    </article>
</template>
