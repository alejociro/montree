<script setup lang="ts">
import { CalendarPlus, Loader2, MapPin, Pencil, Trash2, Truck, UserRound, Building2, Ban } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import CancelTourDateController from '@/actions/App/Http/Controllers/Api/V1/Admin/CancelTourDateController';
import { index as hotelsIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import { index as providersIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import { index as routesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import {
    destroy as destroyDate,
    index as datesIndex,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateController';
import TourDateFormDialog from '@/components/organisms/TourDateFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type {
    LogisticsRef,
    TourDateAdmin,
    TourDateStatus,
} from '@/types/logistics';

type Props = {
    tourId: number;
    currency: string;
};

const props = defineProps<Props>();

const api = useApi();

type Tab = 'upcoming' | 'past' | 'cancelled';

const dates = ref<TourDateAdmin[]>([]);
const loading = ref(true);
const loadError = ref(false);
const activeTab = ref<Tab>('upcoming');

const guides = ref<LogisticsRef[]>([]);
const routes = ref<LogisticsRef[]>([]);
const providers = ref<LogisticsRef[]>([]);
const hotels = ref<LogisticsRef[]>([]);

const dialogOpen = ref(false);
const editing = ref<TourDateAdmin | null>(null);

const cancelOpen = ref(false);
const cancelTarget = ref<TourDateAdmin | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    return (await response.json()) as T;
}

async function loadDates(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const json = await fetchJson<{ data: TourDateAdmin[] }>(
            datesIndex(props.tourId, { query: { scope: 'all' } }).url,
        );
        dates.value = json.data;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

type TeamMember = { id: number; name: string; role: string | null };

async function loadOptions(): Promise<void> {
    try {
        const [teamJson, routesJson, providersJson, hotelsJson] =
            await Promise.all([
                fetchJson<{ data: TeamMember[] }>(teamIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(routesIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(providersIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(hotelsIndex().url),
            ]);

        guides.value = teamJson.data
            .filter((member) => member.role === 'guide')
            .map((member) => ({ id: member.id, name: member.name }));
        routes.value = routesJson.data.map((route) => ({
            id: route.id,
            name: route.name,
        }));
        providers.value = providersJson.data.map((provider) => ({
            id: provider.id,
            name: provider.name,
        }));
        hotels.value = hotelsJson.data.map((hotel) => ({
            id: hotel.id,
            name: hotel.name,
        }));
    } catch {
        toast.error('No se pudieron cargar las opciones de condiciones.');
    }
}

function isUpcoming(date: TourDateAdmin): boolean {
    return new Date(date.starts_at).getTime() > Date.now();
}

const upcomingDates = computed(() =>
    dates.value.filter(
        (date) => date.status !== 'cancelled' && isUpcoming(date),
    ),
);

const pastDates = computed(() =>
    dates.value.filter(
        (date) => date.status !== 'cancelled' && !isUpcoming(date),
    ),
);

const cancelledDates = computed(() =>
    dates.value.filter((date) => date.status === 'cancelled'),
);

const visibleDates = computed(() => {
    if (activeTab.value === 'past') {
        return pastDates.value;
    }

    if (activeTab.value === 'cancelled') {
        return cancelledDates.value;
    }

    return upcomingDates.value;
});

const tabs = computed<{ key: Tab; label: string; count: number }[]>(() => [
    { key: 'upcoming', label: 'Próximas', count: upcomingDates.value.length },
    { key: 'past', label: 'Pasadas', count: pastDates.value.length },
    {
        key: 'cancelled',
        label: 'Canceladas',
        count: cancelledDates.value.length,
    },
]);

const statusMeta: Record<
    TourDateStatus,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    open: { label: 'Abierta', variant: 'default' },
    full: { label: 'Completa', variant: 'secondary' },
    closed: { label: 'Cerrada', variant: 'outline' },
    cancelled: { label: 'Cancelada', variant: 'destructive' },
};

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(date: TourDateAdmin): void {
    editing.value = date;
    dialogOpen.value = true;
}

function onSaved(): void {
    void loadDates();
}

function openCancel(date: TourDateAdmin): void {
    cancelTarget.value = date;
    cancelReason.value = '';
    cancelOpen.value = true;
}

function confirmCancel(): void {
    if (!cancelTarget.value || cancelling.value) {
        return;
    }

    cancelling.value = true;

    void api.patch(
        CancelTourDateController(cancelTarget.value.id).url,
        { reason: cancelReason.value.trim() || null },
        {
            onSuccess: () => {
                toast.success('Salida cancelada.');
                cancelOpen.value = false;
                void loadDates();
            },
            onError: (errors) => {
                toast.error(errors._global ?? 'No se pudo cancelar la salida.');
            },
            onFinish: () => {
                cancelling.value = false;
            },
        },
    );
}

function removeDate(date: TourDateAdmin): void {
    if (
        !confirm(
            '¿Eliminar esta salida? Solo se puede si no tiene reservas asociadas.',
        )
    ) {
        return;
    }

    void api.delete(destroyDate(date.id).url, {
        onSuccess: () => {
            toast.success('Salida eliminada.');
            void loadDates();
        },
        onError: (errors) => {
            toast.error(errors._global ?? 'No se pudo eliminar la salida.');
        },
    });
}

function priceLabel(date: TourDateAdmin): string {
    return formatCurrency(date.effective_price, props.currency);
}

onMounted(() => {
    void loadDates();
    void loadOptions();
});
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-border bg-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Salidas</h2>
                <p class="text-sm text-muted-foreground">
                    Programá las fechas de este producto con sus condiciones.
                </p>
            </div>
            <Button size="sm" @click="openCreate">
                <CalendarPlus class="size-4" />
                Nueva salida
            </Button>
        </div>

        <div class="flex gap-1 rounded-lg bg-muted p-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="
                    activeTab === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
        </div>

        <div v-if="loading" class="space-y-2">
            <div
                v-for="n in 3"
                :key="n"
                class="h-16 animate-pulse rounded-lg bg-muted"
            />
        </div>

        <div
            v-else-if="loadError"
            class="rounded-lg border border-destructive/40 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">
                No se pudieron cargar las salidas.
            </p>
            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                @click="loadDates"
            >
                Reintentar
            </Button>
        </div>

        <div
            v-else-if="visibleDates.length === 0"
            class="rounded-lg border border-dashed border-border p-8 text-center"
        >
            <CalendarPlus class="mx-auto size-8 text-muted-foreground/40" />
            <p class="mt-3 font-medium text-foreground">
                {{
                    activeTab === 'upcoming'
                        ? 'Sin salidas próximas'
                        : activeTab === 'past'
                          ? 'Sin salidas pasadas'
                          : 'Sin salidas canceladas'
                }}
            </p>
            <p
                v-if="activeTab === 'upcoming'"
                class="mt-1 text-sm text-muted-foreground"
            >
                Creá la primera salida para que aparezca en el catálogo.
            </p>
        </div>

        <ul v-else class="space-y-3">
            <li
                v-for="date in visibleDates"
                :key="date.id"
                class="rounded-xl border border-border bg-background p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-foreground capitalize">
                                {{ formatTourDate(date.starts_at) }}
                            </p>
                            <Badge :variant="statusMeta[date.status].variant">
                                {{ statusMeta[date.status].label }}
                            </Badge>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span>
                                {{ date.booked_count }}/{{ date.capacity }}
                                reservados · {{ date.available_seats }} cupos
                            </span>
                            <span class="font-medium text-foreground">
                                {{ priceLabel(date) }}
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 pt-1 text-xs text-muted-foreground"
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

                    <div
                        v-if="date.status !== 'cancelled'"
                        class="flex items-center gap-1"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Editar"
                            @click="openEdit(date)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Cancelar salida"
                            @click="openCancel(date)"
                        >
                            <Ban class="size-4 text-amber-600" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Eliminar"
                            @click="removeDate(date)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </div>
            </li>
        </ul>

        <TourDateFormDialog
            v-model:open="dialogOpen"
            :tour-id="props.tourId"
            :editing="editing"
            :guides="guides"
            :routes="routes"
            :providers="providers"
            :hotels="hotels"
            @saved="onSaved"
        />

        <Dialog v-model:open="cancelOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cancelar salida</DialogTitle>
                    <DialogDescription>
                        La salida dejará de mostrarse en el catálogo público. Las
                        reservas existentes no se modifican.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="cancel-reason">Motivo (opcional)</Label>
                    <Textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        rows="3"
                        placeholder="Ej: clima adverso"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="cancelling"
                        @click="cancelOpen = false"
                    >
                        Volver
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="cancelling"
                        @click="confirmCancel"
                    >
                        <Loader2
                            v-if="cancelling"
                            class="size-4 animate-spin"
                        />
                        Cancelar salida
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
