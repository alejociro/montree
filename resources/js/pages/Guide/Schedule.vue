<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ClipboardList, RefreshCw } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { schedule as scheduleUrl } from '@/actions/App/Http/Controllers/Api/V1/GuideController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import { intlLocale } from '@/lib/format';
import { passengers as passengersRoute } from '@/routes/guide';
import { show as guideTourRoute } from '@/routes/guide/tours';

const { t } = useTranslations();

type ScheduleItem = {
    id: number;
    starts_at: string;
    ends_at: string | null;
    capacity_total: number;
    capacity_booked: number;
    tour: { id: number; name: string; slug: string };
};

const items = ref<ScheduleItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

/**
 * WHY: el diálogo de viajeros que vivía aquí desapareció con la Fase 3. Servía
 * un endpoint recortado (nombre, email y teléfono) que se eliminó, y la planilla
 * completa —documento, emergencia, EPS y observaciones— es ahora una pantalla
 * propia. Desde la agenda se entra a ella, no se asoma.
 */
async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(scheduleUrl().url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const payload = (await response.json()) as { data: ScheduleItem[] };
        items.value = payload.data;
    } catch {
        error.value = t('No pudimos cargar tu agenda.');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void load();
});

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(intlLocale(), {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatTime(date: string): string {
    return new Date(date).toLocaleTimeString(intlLocale(), {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="container mx-auto max-w-3xl space-y-4 px-4 py-8">
        <Head :title="$t('Mi agenda')" />
        <h1 class="text-2xl font-bold">{{ $t('Mi agenda') }}</h1>

        <div
            v-if="error"
            class="flex flex-col items-center gap-3 rounded-xl border border-brand-drop/30 bg-brand-drop-50 p-8 text-center"
        >
            <AlertTriangle class="size-8 text-brand-drop" />
            <p class="text-sm font-medium text-brand-drop">{{ error }}</p>
            <Button variant="outline" size="sm" @click="load">
                <RefreshCw class="size-4" />
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <div v-else-if="loading" class="space-y-3">
            <Skeleton
                v-for="row in 3"
                :key="row"
                class="h-24 w-full rounded-lg"
            />
        </div>

        <div
            v-else-if="items.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
            {{ $t('No tienes tours asignados próximamente.') }}
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="d in items"
                :key="d.id"
                class="space-y-3 rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <Link
                            :href="guideTourRoute(d.tour.id).url"
                            class="font-medium underline-offset-4 hover:underline"
                        >
                            {{ d.tour.name }}
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            {{ formatDate(d.starts_at) }} ·
                            {{ formatTime(d.starts_at) }}
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {{
                            $t(':booked/:total viajeros', {
                                booked: d.capacity_booked,
                                total: d.capacity_total,
                            })
                        }}
                    </Badge>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="passengersRoute(d.id).url">
                        <Button variant="outline" size="sm">
                            <ClipboardList class="size-4" />
                            {{ $t('Ver planilla de pasajeros') }}
                        </Button>
                    </Link>
                    <Link :href="guideTourRoute(d.tour.id).url">
                        <Button variant="ghost" size="sm">
                            {{ $t('Ver el detalle del tour') }}
                        </Button>
                    </Link>
                </div>
            </li>
        </ul>
    </div>
</template>
