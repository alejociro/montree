<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarClock } from 'lucide-vue-next';
import { computed } from 'vue';
import PassengerManifest from '@/components/organisms/PassengerManifest.vue';
import { Badge } from '@/components/ui/badge';
import { useTranslations } from '@/composables/useTranslations';
import { formatTourDate } from '@/lib/format';
import { schedule as scheduleRoute } from '@/routes/guide';
import { show as guideTourRoute } from '@/routes/guide/tours';

const { t } = useTranslations();

type Departure = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    status: string;
    tour: { id: number; name: string };
};

const props = defineProps<{ departure: Departure }>();

const isCancelled = computed(() => props.departure.status === 'cancelled');

const manifestSource = computed(
    () => ({ kind: 'departure', tourDateId: props.departure.id }) as const,
);

const title = computed(() =>
    t('Pasajeros · :tour', { tour: props.departure.tour.name }),
);
</script>

<template>
    <div class="container mx-auto max-w-6xl space-y-6 px-4 py-8">
        <Head :title="title" />

        <div class="print:hidden">
            <Link
                :href="scheduleRoute().url"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                {{ $t('Volver a mi agenda') }}
            </Link>
        </div>

        <header class="space-y-2 print:hidden">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ props.departure.tour.name }}
                </h1>
                <Badge v-if="isCancelled" variant="destructive">
                    {{ $t('Salida cancelada') }}
                </Badge>
            </div>
            <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                <CalendarClock class="size-4" />
                {{ formatTourDate(props.departure.starts_at) }}
            </p>
            <Link
                :href="guideTourRoute(props.departure.tour.id).url"
                class="inline-block text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                {{ $t('Ver el detalle del tour') }}
            </Link>
        </header>

        <p
            v-if="isCancelled"
            class="rounded-lg border border-brand-warn/30 bg-brand-warn-50 px-4 py-3 text-sm text-brand-warn print:hidden"
        >
            {{
                $t(
                    'Esta salida está cancelada. La planilla se conserva solo como consulta.',
                )
            }}
        </p>

        <!--
          `readonly`: el guía SOLO lee (D1). Ni crear, ni editar, ni registrar
          pagos — esas acciones no se dibujan, no se deshabilitan.
        -->
        <PassengerManifest
            :source="manifestSource"
            readonly
            :title="props.departure.tour.name"
        />
    </div>
</template>
