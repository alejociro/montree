<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Ban,
    CalendarClock,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Eye,
    Loader2,
    Pencil,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { show as tourShowPage } from '@/actions/App/Http/Controllers/Admin/TourPagesController';
import CancelTourDateController from '@/actions/App/Http/Controllers/Api/V1/Admin/CancelTourDateController';
import { index as hotelsIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import { index as providersIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import RestoreTourDateController from '@/actions/App/Http/Controllers/Api/V1/Admin/RestoreTourDateController';
import { index as routesIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import { index as teamIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import { index as adminToursIndex } from '@/actions/App/Http/Controllers/Api/V1/Admin/TourController';
import TourDateIndexController from '@/actions/App/Http/Controllers/Api/V1/Admin/TourDateIndexController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import FilterBar from '@/components/molecules/FilterBar.vue';
import DepartureDetailSheet from '@/components/organisms/DepartureDetailSheet.vue';
import TourDateFormDialog from '@/components/organisms/TourDateFormDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
import {
    formatCurrency,
    formatDayDistance,
    formatDayMonth,
    formatNumber,
    formatWeekdayTime,
} from '@/lib/format';
import type {
    DepartureBoardStats,
    DepartureBoardTotals,
    DepartureScopeId,
    LogisticsRef,
    PaginationMeta,
    TourDateGlobalAdmin,
    TourDatesGlobalResponse,
} from '@/types/logistics';
import type { TeamListResponse, TeamMemberPayload } from '@/types/team';

const { t } = useTranslations();

const api = useApi();

// 10 filas por página: el pedido es paginar a partir del undécimo registro.
const PER_PAGE = 10;
const ALL_TOURS = 'all';

const dates = ref<TourDateGlobalAdmin[]>([]);
const meta = ref<PaginationMeta | null>(null);
const stats = ref<DepartureBoardStats | null>(null);
const counts = ref<Partial<Record<DepartureScopeId, number>>>({});
const totals = ref<DepartureBoardTotals | null>(null);
const currentPage = ref(1);
const loading = ref(true);
const loadError = ref(false);

const filters = reactive({
    scope: 'upcoming' as DepartureScopeId,
    search: '',
    tourId: ALL_TOURS as string,
    direction: 'asc' as 'asc' | 'desc',
});

type TourOption = { id: number; name: string };

const tourOptions = ref<TourOption[]>([]);

const guides = ref<LogisticsRef[]>([]);
const routes = ref<LogisticsRef[]>([]);
const providers = ref<LogisticsRef[]>([]);
const hotels = ref<LogisticsRef[]>([]);

const dialogOpen = ref(false);
const editing = ref<TourDateGlobalAdmin | null>(null);

const detailOpen = ref(false);
const detail = ref<TourDateGlobalAdmin | null>(null);

const cancelOpen = ref(false);
const cancelTarget = ref<TourDateGlobalAdmin | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);
const restoringId = ref<number | null>(null);

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
    scope: DepartureScopeId;
    search?: string;
    tour_id?: number;
};

function buildQuery(): ListQuery {
    const query: ListQuery = {
        page: currentPage.value,
        per_page: PER_PAGE,
        direction: filters.direction,
        scope: filters.scope,
    };

    if (filters.search.trim() !== '') {
        query.search = filters.search.trim();
    }

    if (filters.tourId !== ALL_TOURS) {
        query.tour_id = Number(filters.tourId);
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
        stats.value = response.stats;
        counts.value = response.counts;
        totals.value = response.totals;
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

const SCOPES: { id: DepartureScopeId; label: string }[] = [
    { id: 'upcoming', label: t('Próximas') },
    { id: 'today', label: t('Hoy') },
    { id: 'past', label: t('Realizadas') },
    { id: 'disabled', label: t('Inhabilitadas') },
    { id: 'all', label: t('Todas') },
];

const scopeTabs = computed<CountTab[]>(() =>
    SCOPES.map((scope) => ({
        id: scope.id,
        label: scope.label,
        count: counts.value[scope.id] ?? null,
    })),
);

const resultLabel = computed<string | null>(() => {
    if (meta.value === null) {
        return null;
    }

    const total = counts.value.all ?? meta.value.total;

    return t(':shown de :total', {
        shown: formatNumber(meta.value.total),
        total: formatNumber(total),
    });
});

function durationLabel(date: TourDateGlobalAdmin): string {
    if (date.ends_at === null) {
        return '';
    }

    const start = new Date(date.starts_at);
    const end = new Date(date.ends_at);
    const days =
        Math.round((end.getTime() - start.getTime()) / (24 * 60 * 60 * 1000)) +
        1;

    return days > 1 ? t(':count días', { count: days }) : t('1 día');
}

function subtitleFor(date: TourDateGlobalAdmin): string {
    const parts = [date.code];
    const duration = durationLabel(date);

    if (duration !== '') {
        parts.push(duration);
    }

    if (isDisabled(date)) {
        parts.push(t('inhabilitada'));
    }

    return parts.join(' · ');
}

function priceLabel(date: TourDateGlobalAdmin): string {
    return formatCurrency(date.effective_price, date.tour.currency);
}

function occupancyPercent(date: TourDateGlobalAdmin): number {
    if (date.capacity <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((date.booked_count / date.capacity) * 100));
}

/**
 * La barra sigue al sistema de diseño: tinta cuando ya no queda cupo, línea
 * cuando no se ha vendido nada, y el color de la agencia en el medio.
 */
function occupancyBarClass(date: TourDateGlobalAdmin): string {
    const percent = occupancyPercent(date);

    if (percent >= 100) {
        return 'bg-brand-ink';
    }

    return percent === 0 ? 'bg-border' : 'bg-primary';
}

function isDisabled(date: TourDateGlobalAdmin): boolean {
    return date.display_status === 'cancelled';
}

/** Una salida ya realizada no se edita ni se inhabilita: solo se consulta. */
function canManage(date: TourDateGlobalAdmin): boolean {
    return date.display_status !== 'finished';
}

const hasActiveFilters = computed(
    () =>
        filters.search.trim() !== '' ||
        filters.tourId !== ALL_TOURS ||
        filters.scope !== 'upcoming',
);

function openEdit(date: TourDateGlobalAdmin): void {
    editing.value = date;
    dialogOpen.value = true;
}

function openDetail(date: TourDateGlobalAdmin): void {
    detail.value = date;
    detailOpen.value = true;
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
                toast.success(t('Salida inhabilitada.'));
                cancelOpen.value = false;
                void loadDates();
            },
            onError: (errors) => {
                toast.error(
                    errors._global ?? t('No se pudo inhabilitar la salida.'),
                );
            },
            onFinish: () => {
                cancelling.value = false;
            },
        },
    );
}

function restore(date: TourDateGlobalAdmin): void {
    if (restoringId.value !== null) {
        return;
    }

    restoringId.value = date.id;

    void api.patch(
        RestoreTourDateController(date.id).url,
        {},
        {
            onSuccess: () => {
                toast.success(t('Salida habilitada.'));
                void loadDates();
            },
            onError: (errors) => {
                toast.error(
                    errors._global ?? t('No se pudo habilitar la salida.'),
                );
            },
            onFinish: () => {
                restoringId.value = null;
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
    filters.scope = 'upcoming';
    filters.search = '';
    filters.tourId = ALL_TOURS;
    filters.direction = 'asc';
}

function handleTourChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        filters.tourId = value;
    }
}

function handleDirectionChange(value: AcceptableValue): void {
    if (typeof value === 'string') {
        filters.direction = value === 'desc' ? 'desc' : 'asc';
    }
}

// El buscador espera a que la persona deje de escribir; el resto de filtros
// dispara de inmediato.
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(
    () => filters.search,
    () => {
        if (searchDebounce) {
            clearTimeout(searchDebounce);
        }

        searchDebounce = setTimeout(() => {
            currentPage.value = 1;
            void loadDates();
        }, 300);
    },
);

watch(
    () => [filters.scope, filters.tourId, filters.direction],
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
                        'Cada salida es una fecha real con su cupo, su precio y su guía.',
                    )
                "
            />

            <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    :label="$t('Salidas activas')"
                    :value="formatNumber(stats?.active ?? 0)"
                    :detail="$t('próximas y habilitadas')"
                    :loading="stats === null"
                />
                <KpiCard
                    :label="$t('Cupos por vender')"
                    :value="formatNumber(stats?.seats_left ?? 0)"
                    :detail="$t('en salidas futuras')"
                    :loading="stats === null"
                />
                <KpiCard
                    :label="$t('Viajeros confirmados')"
                    :value="formatNumber(stats?.travellers ?? 0)"
                    :detail="$t('con reserva activa')"
                    :loading="stats === null"
                />
                <KpiCard
                    :label="$t('Sin guía asignado')"
                    :value="formatNumber(stats?.without_guide ?? 0)"
                    :detail="$t('requieren asignación')"
                    :alert="(stats?.without_guide ?? 0) > 0"
                    :loading="stats === null"
                />
            </div>

            <FilterBar
                class="mt-5"
                search-id="departures-search"
                :search="filters.search"
                :placeholder="$t('Buscar salida por tour, código o guía')"
                :result-label="resultLabel"
                :tabs="scopeTabs"
                :active-tab="filters.scope"
                :tabs-label="$t('Bandejas de salidas')"
                @update:search="filters.search = $event"
                @update:active-tab="filters.scope = $event as DepartureScopeId"
            >
                <template #selects>
                    <div class="flex items-center gap-2">
                        <Label
                            for="filter-tour"
                            class="text-[10.5px] font-semibold tracking-[0.09em] text-muted-foreground uppercase"
                        >
                            {{ $t('Tour') }}
                        </Label>
                        <Select
                            :model-value="filters.tourId"
                            @update:model-value="handleTourChange"
                        >
                            <SelectTrigger
                                id="filter-tour"
                                class="w-[190px] rounded-full"
                            >
                                <SelectValue :placeholder="$t('Todos')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="ALL_TOURS">
                                        {{ $t('Todos') }}
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

                    <div class="flex items-center gap-2">
                        <Label
                            for="filter-direction"
                            class="text-[10.5px] font-semibold tracking-[0.09em] text-muted-foreground uppercase"
                        >
                            {{ $t('Orden') }}
                        </Label>
                        <Select
                            :model-value="filters.direction"
                            @update:model-value="handleDirectionChange"
                        >
                            <SelectTrigger
                                id="filter-direction"
                                class="w-[160px] rounded-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="asc">
                                        {{ $t('Más próxima') }}
                                    </SelectItem>
                                    <SelectItem value="desc">
                                        {{ $t('Más lejana') }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </template>
            </FilterBar>

            <div class="mt-4 rounded-2xl border border-border bg-card">
                <div v-if="loading" class="space-y-2 p-4">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="h-14 animate-pulse rounded-lg bg-muted"
                    />
                </div>

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

                <div
                    v-else-if="dates.length === 0"
                    class="m-4 flex flex-col items-center gap-3 rounded-xl border border-dashed border-input p-12 text-center"
                >
                    <CalendarClock class="size-8 text-muted-foreground/40" />
                    <div class="space-y-1">
                        <p class="text-base font-medium text-foreground">
                            {{
                                hasActiveFilters
                                    ? $t('Sin salidas para este filtro')
                                    : $t('Todavía no hay salidas')
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? $t(
                                          'Prueba con otra bandeja o limpia el buscador.',
                                      )
                                    : $t(
                                          'Programa salidas desde la pestaña Salidas del tour.',
                                      )
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

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[880px] text-sm">
                        <thead>
                            <tr class="border-b border-border text-left">
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Tour')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Fecha')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Precio')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Guía')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Ocupación')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3 text-right">
                                    {{ $t('Acciones') }}
                                </MonoLabel>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="date in dates"
                                :key="date.id"
                                class="border-b border-brand-line-2 align-middle transition last:border-0 hover:bg-primary-soft/50"
                                :class="isDisabled(date) ? 'opacity-[.62]' : ''"
                            >
                                <td class="px-4 py-3.5">
                                    <Link
                                        :href="tourShowPage(date.tour.id).url"
                                        class="text-[14.5px] font-semibold text-foreground underline-offset-4 hover:underline"
                                    >
                                        {{ date.tour.name }}
                                    </Link>
                                    <MonoLabel class="mt-1">{{
                                        subtitleFor(date)
                                    }}</MonoLabel>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid w-[46px] shrink-0 place-items-center rounded-lg border border-border bg-background py-1"
                                        >
                                            <span
                                                class="text-base leading-none font-semibold tabular-nums"
                                            >
                                                {{
                                                    formatDayMonth(
                                                        date.starts_at,
                                                    ).day
                                                }}
                                            </span>
                                            <span
                                                class="mt-0.5 text-[10px] font-semibold tracking-[0.09em] text-muted-foreground"
                                            >
                                                {{
                                                    formatDayMonth(
                                                        date.starts_at,
                                                    ).month
                                                }}
                                            </span>
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block text-[13px] font-medium text-foreground"
                                            >
                                                {{
                                                    formatWeekdayTime(
                                                        date.starts_at,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                {{
                                                    formatDayDistance(
                                                        date.starts_at,
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        class="block text-[15px] font-bold tabular-nums"
                                    >
                                        {{ priceLabel(date) }}
                                    </span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ $t('por persona') }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div
                                        v-if="date.guide"
                                        class="flex items-center gap-2"
                                    >
                                        <InitialsAvatar
                                            :name="date.guide.name"
                                            size="sm"
                                        />
                                        <span class="min-w-0">
                                            <span
                                                class="block text-[13px] font-medium text-foreground"
                                            >
                                                {{ date.guide.name }}
                                            </span>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                {{ $t('asignado') }}
                                            </span>
                                        </span>
                                    </div>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-brand-drop-50 px-2.5 py-1 text-[11.5px] font-semibold text-brand-drop"
                                    >
                                        {{ $t('Sin guía') }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div
                                        class="flex w-[140px] items-baseline justify-between gap-2"
                                    >
                                        <span
                                            class="text-[13px] font-semibold tabular-nums"
                                        >
                                            {{ date.booked_count }}/{{
                                                date.capacity
                                            }}
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                $t(':count libres', {
                                                    count: date.available_seats,
                                                })
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-1.5 h-1.5 w-[140px] overflow-hidden rounded-full bg-brand-line-2"
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

                                <td class="px-4 py-3.5">
                                    <div class="flex justify-end">
                                        <ActionMenu
                                            variant="ghost"
                                            :label="
                                                $t('Acciones de :name', {
                                                    name: date.tour.name,
                                                })
                                            "
                                        >
                                            <DropdownMenuItem
                                                @select="openDetail(date)"
                                            >
                                                <Eye class="size-4" />
                                                {{ $t('Ver detalle') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    canManage(date) &&
                                                    !isDisabled(date)
                                                "
                                                @select="openEdit(date)"
                                            >
                                                <Pencil class="size-4" />
                                                {{ $t('Editar salida') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="isDisabled(date)"
                                                :disabled="
                                                    restoringId === date.id
                                                "
                                                @select="restore(date)"
                                            >
                                                <CheckCircle2 class="size-4" />
                                                {{ $t('Habilitar') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-else-if="canManage(date)"
                                                variant="destructive"
                                                @select="openCancel(date)"
                                            >
                                                <Ban class="size-4" />
                                                {{ $t('Inhabilitar') }}
                                            </DropdownMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="totals">
                            <tr class="border-t border-border bg-background/60">
                                <td
                                    colspan="6"
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <!--
                                      Tres frases con su propio plural: una sola
                                      cadena con tres números daba «1 viajeros».
                                    -->
                                    {{
                                        $tc(
                                            ':count salida|:count salidas',
                                            totals.departures,
                                        )
                                    }}
                                    ·
                                    {{
                                        $tc(
                                            ':count viajero|:count viajeros',
                                            totals.travellers,
                                        )
                                    }}
                                    ·
                                    {{
                                        $tc(
                                            ':count cupo libre|:count cupos libres',
                                            totals.seats_left,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

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
                    <div
                        v-if="meta.last_page > 1"
                        class="flex items-center gap-2"
                    >
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

        <DepartureDetailSheet
            v-model:open="detailOpen"
            :departure="detail"
            @edit="
                (value) => {
                    detailOpen = false;
                    openEdit(value);
                }
            "
        />

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
                    <DialogTitle>{{ $t('Inhabilitar salida') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'La salida dejará de mostrarse en el catálogo público. Las reservas existentes no se modifican y podrás volver a habilitarla.',
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
                        {{ $t('Inhabilitar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
