<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Loader2,
    MapPin,
    Pencil,
    Truck,
    UserRound,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { show as tourShowPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import CancelTourDateController from '@/actions/App/Http/Controllers/Api/V1/Admin/CancelTourDateController';
import { index as hotelsIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import { index as providersIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import { index as routesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import { index as adminToursIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import TourDateIndexController from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateIndexController';
import Heading from '@/components/Heading.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import { formatCurrency, formatTourDate } from '@/lib/format';
import type {
    LogisticsRef,
    PaginationMeta,
    TourDateDisplayStatus,
    TourDateGlobalAdmin,
    TourDatesGlobalResponse,
} from '@/types/logistics';
import type { TeamListResponse, TeamMemberPayload } from '@/types/team';

const { t } = useTranslations();

const api = useApi();

const PER_PAGE = 15;
const ALL = 'all';

const dates = ref<TourDateGlobalAdmin[]>([]);
const meta = ref<PaginationMeta | null>(null);
const currentPage = ref(1);
const loading = ref(true);
const loadError = ref(false);

const filters = reactive({
    status: ALL as TourDateDisplayStatus | typeof ALL,
    tourId: ALL as string,
    from: '',
    to: '',
    direction: 'desc' as 'asc' | 'desc',
});

type TourOption = { id: number; name: string };

const tourOptions = ref<TourOption[]>([]);

const guides = ref<LogisticsRef[]>([]);
const routes = ref<LogisticsRef[]>([]);
const providers = ref<LogisticsRef[]>([]);
const hotels = ref<LogisticsRef[]>([]);

const dialogOpen = ref(false);
const editing = ref<TourDateGlobalAdmin | null>(null);

const cancelOpen = ref(false);
const cancelTarget = ref<TourDateGlobalAdmin | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return (await response.json()) as T;
}

type ListQuery = {
    page: number;
    per_page: number;
    direction: 'asc' | 'desc';
    status?: TourDateDisplayStatus;
    tour_id?: number;
    from?: string;
    to?: string;
};

function buildQuery(): ListQuery {
    const query: ListQuery = {
        page: currentPage.value,
        per_page: PER_PAGE,
        direction: filters.direction,
    };

    if (filters.status !== ALL) {
        query.status = filters.status;
    }

    if (filters.tourId !== ALL) {
        query.tour_id = Number(filters.tourId);
    }

    if (filters.from !== '') {
        query.from = filters.from;
    }

    if (filters.to !== '') {
        query.to = filters.to;
    }

    return query;
}

async function loadDates(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetchJson<TourDatesGlobalResponse>(
            TourDateIndexController.url({ query: buildQuery() }),
        );
        dates.value = response.data;
        meta.value = response.meta;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

/**
 * Desde F018 Fase 3A un miembro tiene varios roles y cada uno viaja como objeto
 * (`RoleSummaryResource`), no como string: hay que mirar `name`.
 */
function isGuide(member: TeamMemberPayload): boolean {
    return (member.roles ?? []).some((role) => role.name === 'guide');
}

async function loadTours(): Promise<void> {
    try {
        const response = await fetchJson<{ data: TourOption[] }>(
            adminToursIndex({ query: { per_page: 100 } }).url,
        );
        tourOptions.value = response.data.map((tour) => ({
            id: tour.id,
            name: tour.name,
        }));
    } catch {
        toast.error(t('No se pudieron cargar los tours para filtrar.'));
    }
}

async function loadOptions(): Promise<void> {
    try {
        const [teamJson, routesJson, providersJson, hotelsJson] =
            await Promise.all([
                fetchJson<TeamListResponse>(teamIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(routesIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(providersIndex().url),
                fetchJson<{ data: LogisticsRef[] }>(hotelsIndex().url),
            ]);

        guides.value = teamJson.data
            .filter(isGuide)
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
        toast.error(t('No se pudieron cargar las opciones de condiciones.'));
    }
}

const statusOptions: { value: TourDateDisplayStatus; label: string }[] = [
    { value: 'open', label: t('Abierta') },
    { value: 'full', label: t('Llena') },
    { value: 'closed', label: t('Cerrada') },
    { value: 'in_progress', label: t('En curso') },
    { value: 'finished', label: t('Finalizada') },
    { value: 'cancelled', label: t('Cancelada') },
];

const statusMeta: Record<
    TourDateDisplayStatus,
    { label: string; classes: string }
> = {
    open: {
        label: t('Abierta'),
        classes:
            'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    },
    full: {
        label: t('Llena'),
        classes:
            'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    closed: {
        label: t('Cerrada'),
        classes:
            'border-transparent bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    },
    in_progress: {
        label: t('En curso'),
        classes:
            'border-transparent bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    },
    finished: {
        label: t('Finalizada'),
        classes:
            'border-transparent bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    },
    cancelled: {
        label: t('Cancelada'),
        classes:
            'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    },
};

function priceLabel(date: TourDateGlobalAdmin): string {
    return formatCurrency(date.effective_price, date.tour.currency);
}

function occupancyPercent(date: TourDateGlobalAdmin): number {
    if (date.capacity <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((date.booked_count / date.capacity) * 100));
}

function occupancyBarClass(date: TourDateGlobalAdmin): string {
    const percent = occupancyPercent(date);

    if (percent >= 100) {
        return 'bg-red-500';
    }

    if (percent >= 80) {
        return 'bg-amber-500';
    }

    return 'bg-primary';
}

function canManage(date: TourDateGlobalAdmin): boolean {
    return (
        date.display_status !== 'cancelled' &&
        date.display_status !== 'finished'
    );
}

function conditionsFor(date: TourDateGlobalAdmin): string[] {
    const items: string[] = [];

    if (date.route) {
        items.push(date.route.name);
    }

    if (date.provider) {
        items.push(date.provider.name);
    }

    if (date.hotels.length > 0) {
        items.push(...date.hotels.map((hotel) => hotel.name));
    }

    return items;
}

const hasActiveFilters = computed(
    () =>
        filters.status !== ALL ||
        filters.tourId !== ALL ||
        filters.from !== '' ||
        filters.to !== '',
);

function openEdit(date: TourDateGlobalAdmin): void {
    editing.value = date;
    dialogOpen.value = true;
}

function onSaved(): void {
    void loadDates();
}

function openCancel(date: TourDateGlobalAdmin): void {
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
                toast.success(t('Salida cancelada.'));
                cancelOpen.value = false;
                void loadDates();
            },
            onError: (errors) => {
                toast.error(
                    errors._global ?? t('No se pudo cancelar la salida.'),
                );
            },
            onFinish: () => {
                cancelling.value = false;
            },
        },
    );
}

function goToPage(page: number): void {
    if (!meta.value || page < 1 || page > meta.value.last_page) {
        return;
    }

    currentPage.value = page;
    void loadDates();
}

function resetFilters(): void {
    filters.status = ALL;
    filters.tourId = ALL;
    filters.from = '';
    filters.to = '';
    filters.direction = 'desc';
}

function handleStatusChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    filters.status = value as TourDateDisplayStatus | typeof ALL;
}

function handleTourChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    filters.tourId = value;
}

function handleDirectionChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    filters.direction = value === 'asc' ? 'asc' : 'desc';
}

watch(
    () => ({ ...filters }),
    () => {
        currentPage.value = 1;
        void loadDates();
    },
);

onMounted(() => {
    void loadDates();
    void loadTours();
    void loadOptions();
});
</script>

<template>
    <div>
        <Head :title="$t('Salidas')" />

        <div class="px-4 py-6 md:px-8">
            <Heading
                :title="$t('Salidas')"
                :description="
                    $t(
                        'Todas las salidas programadas de tus tours, con su ocupación y estado.',
                    )
                "
            />

            <div class="mt-6 rounded-2xl border border-border bg-card">
                <!-- Filters -->
                <div
                    class="flex flex-wrap items-end gap-3 border-b border-border p-4"
                >
                    <div class="space-y-1.5">
                        <Label for="filter-status">{{ $t('Estado') }}</Label>
                        <Select
                            :model-value="filters.status"
                            @update:model-value="handleStatusChange"
                        >
                            <SelectTrigger id="filter-status" class="w-[160px]">
                                <SelectValue :placeholder="$t('Todos')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="ALL">
                                        {{ $t('Todos los estados') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-tour">{{ $t('Tour') }}</Label>
                        <Select
                            :model-value="filters.tourId"
                            @update:model-value="handleTourChange"
                        >
                            <SelectTrigger id="filter-tour" class="w-[200px]">
                                <SelectValue :placeholder="$t('Todos')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="ALL">
                                        {{ $t('Todos los tours') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="tour in tourOptions"
                                        :key="tour.id"
                                        :value="String(tour.id)"
                                    >
                                        {{ tour.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-from">{{ $t('Desde') }}</Label>
                        <Input
                            id="filter-from"
                            v-model="filters.from"
                            type="date"
                            class="w-[160px]"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-to">{{ $t('Hasta') }}</Label>
                        <Input
                            id="filter-to"
                            v-model="filters.to"
                            type="date"
                            class="w-[160px]"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-direction">{{ $t('Orden') }}</Label>
                        <Select
                            :model-value="filters.direction"
                            @update:model-value="handleDirectionChange"
                        >
                            <SelectTrigger
                                id="filter-direction"
                                class="w-[160px]"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="desc">
                                        {{ $t('Más recientes') }}
                                    </SelectItem>
                                    <SelectItem value="asc">
                                        {{ $t('Más antiguas') }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="ml-auto"
                        @click="resetFilters"
                    >
                        {{ $t('Limpiar filtros') }}
                    </Button>
                </div>

                <!-- Loading -->
                <div v-if="loading" class="space-y-2 p-4">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="h-14 animate-pulse rounded-lg bg-muted"
                    />
                </div>

                <!-- Error -->
                <div v-else-if="loadError" class="p-10 text-center">
                    <p class="text-sm text-destructive">
                        {{ $t('No se pudieron cargar las salidas.') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="loadDates"
                    >
                        {{ $t('Reintentar') }}
                    </Button>
                </div>

                <!-- Empty -->
                <div
                    v-else-if="dates.length === 0"
                    class="flex flex-col items-center gap-3 p-12 text-center"
                >
                    <CalendarClock class="size-8 text-muted-foreground/40" />
                    <div class="space-y-1">
                        <p class="font-medium text-foreground">
                            {{
                                hasActiveFilters
                                    ? 'Sin salidas para estos filtros'
                                    : 'Todavía no hay salidas'
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? 'Prueba ajustar o limpiar los filtros.'
                                    : 'Programa salidas desde el detalle de cada tour.'
                            }}
                        </p>
                    </div>
                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        size="sm"
                        @click="resetFilters"
                    >
                        {{ $t('Limpiar filtros') }}
                    </Button>
                </div>

                <!-- Table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs font-medium text-muted-foreground"
                            >
                                <th class="px-4 py-3">{{ $t('Tour') }}</th>
                                <th class="px-4 py-3">{{ $t('Fecha') }}</th>
                                <th class="px-4 py-3">{{ $t('Ocupación') }}</th>
                                <th class="px-4 py-3">{{ $t('Precio') }}</th>
                                <th class="px-4 py-3">{{ $t('Guía') }}</th>
                                <th class="px-4 py-3">
                                    {{ $t('Condiciones') }}
                                </th>
                                <th class="px-4 py-3">{{ $t('Estado') }}</th>
                                <th class="px-4 py-3 text-right">
                                    {{ $t('Acciones') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="date in dates"
                                :key="date.id"
                                class="border-b border-border align-top last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="tourShowPage(date.tour.id).url"
                                        class="font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        {{ date.tour.name }}
                                    </Link>
                                </td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                >
                                    <span class="text-foreground capitalize">
                                        {{
                                            formatTourDate(date.starts_at, {
                                                withWeekday: false,
                                            })
                                        }}
                                    </span>
                                    <span
                                        v-if="date.ends_at"
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ $t('hasta') }}
                                        {{
                                            formatTourDate(date.ends_at, {
                                                withWeekday: false,
                                            })
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center gap-2 whitespace-nowrap"
                                    >
                                        <span
                                            class="text-foreground tabular-nums"
                                        >
                                            {{ date.booked_count }}/{{
                                                date.capacity
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-1 h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="occupancyBarClass(date)"
                                            :style="{
                                                width: `${occupancyPercent(date)}%`,
                                            }"
                                        />
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 font-medium whitespace-nowrap text-foreground"
                                >
                                    {{ priceLabel(date) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <span
                                        v-if="date.guide"
                                        class="flex items-center gap-1.5 whitespace-nowrap"
                                    >
                                        <UserRound class="size-3.5" />
                                        {{ date.guide.name }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="conditionsFor(date).length > 0"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <Badge
                                            v-if="date.route"
                                            variant="secondary"
                                            class="gap-1 font-normal"
                                        >
                                            <MapPin class="size-3" />
                                            {{ date.route.name }}
                                        </Badge>
                                        <Badge
                                            v-if="date.provider"
                                            variant="secondary"
                                            class="gap-1 font-normal"
                                        >
                                            <Truck class="size-3" />
                                            {{ date.provider.name }}
                                        </Badge>
                                        <Badge
                                            v-for="hotel in date.hotels"
                                            :key="hotel.id"
                                            variant="secondary"
                                            class="gap-1 font-normal"
                                        >
                                            <Building2 class="size-3" />
                                            {{ hotel.name }}
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground">
                                        —
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            statusMeta[date.display_status]
                                                .classes
                                        "
                                    >
                                        {{
                                            statusMeta[date.display_status]
                                                .label
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="canManage(date)"
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :title="$t('Editar')"
                                            @click="openEdit(date)"
                                        >
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :title="$t('Cancelar salida')"
                                            @click="openCancel(date)"
                                        >
                                            <Ban
                                                class="size-4 text-amber-600"
                                            />
                                        </Button>
                                    </div>
                                    <span
                                        v-else
                                        class="block text-right text-muted-foreground"
                                    >
                                        —
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="!loading && !loadError && meta && meta.total > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-sm text-muted-foreground"
                >
                    <span>
                        {{
                            $t('Mostrando :from–:to de :total salidas', {
                                from: meta.from ?? 0,
                                to: meta.to ?? 0,
                                total: meta.total,
                            })
                        }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page <= 1"
                            @click="goToPage(meta.current_page - 1)"
                        >
                            <ChevronLeft class="size-4" />
                            {{ $t('Anterior') }}
                        </Button>
                        <span class="tabular-nums">
                            {{ meta.current_page }} / {{ meta.last_page }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="goToPage(meta.current_page + 1)"
                        >
                            {{ $t('Siguiente') }}
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <TourDateFormDialog
            v-model:open="dialogOpen"
            :tour-id="editing?.tour.id ?? 0"
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
                    <DialogTitle>{{ $t('Cancelar salida') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'La salida dejará de mostrarse en el catálogo público. Las reservas existentes no se modifican.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="cancel-reason">{{
                        $t('Motivo (opcional)')
                    }}</Label>
                    <Textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        rows="3"
                        :placeholder="$t('Ej: clima adverso')"
                    />
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="cancelling"
                        @click="cancelOpen = false"
                    >
                        {{ $t('Volver') }}
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
                        {{ $t('Cancelar salida') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
