<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Loader2,
    Plus,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AssignGuideController from '@/actions/App/Http/Controllers/Admin/AssignGuideController';
import CancelTourDateController from '@/actions/App/Http/Controllers/Admin/CancelTourDateController';
import { index as departuresIndex } from '@/actions/App/Http/Controllers/Admin/DeparturePagesController';
import RestoreTourDateController from '@/actions/App/Http/Controllers/Admin/RestoreTourDateController';
import { destroy as destroyDeparture } from '@/actions/App/Http/Controllers/Admin/TourDatePagesController';
import KpiCard from '@/components/atoms/KpiCard.vue';
import Heading from '@/components/Heading.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import DepartureBoardFilters from '@/components/organisms/DepartureBoardFilters.vue';
import DepartureBoardTable from '@/components/organisms/DepartureBoardTable.vue';
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
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import type {
    DepartureBoardFilterState,
    DepartureBoardStats,
    DepartureBoardTotals,
    DepartureOptions,
    DepartureScopeId,
    DepartureTourOption,
    PaginationLinks,
    PaginationMeta,
    TourDateGlobalAdmin,
} from '@/types/logistics';

const { t } = useTranslations();

type Props = {
    departures: {
        data: TourDateGlobalAdmin[];
        links: PaginationLinks;
        meta: PaginationMeta;
    };
    filters: DepartureBoardFilterState;
    stats: DepartureBoardStats;
    counts: Record<DepartureScopeId, number>;
    totals: DepartureBoardTotals;
    tours: DepartureTourOption[];
    departureOptions: DepartureOptions;
};

const props = defineProps<Props>();

const ALL_TOURS = 'all';

const search = ref(props.filters.search ?? '');
const scope = ref<DepartureScopeId>(props.filters.scope);
const tourId = ref(
    props.filters.tour_id === null ? ALL_TOURS : String(props.filters.tour_id),
);
const direction = ref<'asc' | 'desc'>(props.filters.direction);
const reloading = ref(false);

function reload(page?: number): void {
    const query: Record<string, string | number> = {
        scope: scope.value,
        direction: direction.value,
    };

    if (search.value.trim() !== '') {
        query.search = search.value.trim();
    }

    if (tourId.value !== ALL_TOURS) {
        query.tour_id = Number(tourId.value);
    }

    if (page !== undefined && page > 1) {
        query.page = page;
    }

    router.get(departuresIndex.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            reloading.value = true;
        },
        onFinish: () => {
            reloading.value = false;
        },
    });
}

// El buscador espera a que la persona deje de escribir; el resto de filtros
// dispara de inmediato.
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
    if (searchDebounce !== null) {
        clearTimeout(searchDebounce);
    }

    searchDebounce = setTimeout(() => reload(), 300);
});

watch([scope, tourId, direction], () => reload());

const SCOPES: { id: DepartureScopeId; label: string }[] = [
    { id: 'upcoming', label: t('Próximas') },
    { id: 'today', label: t('Hoy') },
    { id: 'past', label: t('Realizadas') },
    { id: 'disabled', label: t('Inhabilitadas') },
    { id: 'all', label: t('Todas') },
];

const scopeTabs = computed<CountTab[]>(() =>
    SCOPES.map((item) => ({
        id: item.id,
        label: item.label,
        count: props.counts[item.id] ?? null,
    })),
);

const meta = computed(() => props.departures.meta);

const resultLabel = computed(() =>
    t(':shown de :total', {
        shown: formatNumber(meta.value.total),
        total: formatNumber(props.counts.all ?? meta.value.total),
    }),
);

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        tourId.value !== ALL_TOURS ||
        scope.value !== 'upcoming',
);

function resetFilters(): void {
    search.value = '';
    tourId.value = ALL_TOURS;
    direction.value = 'asc';
    scope.value = 'upcoming';
}

const detailOpen = ref(false);
const detail = ref<TourDateGlobalAdmin | null>(null);

function openDetail(date: TourDateGlobalAdmin): void {
    detail.value = date;
    detailOpen.value = true;
}

const dialogOpen = ref(false);
const editing = ref<TourDateGlobalAdmin | null>(null);
const selectedTourId = ref<number | null>(null);

const selectedTour = computed<DepartureTourOption | null>(
    () => props.tours.find((tour) => tour.id === selectedTourId.value) ?? null,
);

const productPickerOpen = ref(false);
const productPick = ref('');

function openCreate(): void {
    if (props.tours.length === 0) {
        toast.error(t('Crea un producto antes de programar salidas.'));

        return;
    }

    productPick.value = '';
    productPickerOpen.value = true;
}

function handleProductPick(value: AcceptableValue): void {
    if (typeof value === 'string') {
        productPick.value = value;
    }
}

function confirmProduct(): void {
    if (productPick.value === '') {
        return;
    }

    selectedTourId.value = Number(productPick.value);
    editing.value = null;
    productPickerOpen.value = false;
    dialogOpen.value = true;
}

function openEdit(date: TourDateGlobalAdmin): void {
    selectedTourId.value = date.tour.id;

    if (selectedTour.value === null) {
        toast.error(t('No se pudo abrir la salida para editar.'));

        return;
    }

    editing.value = date;
    dialogOpen.value = true;
}

const busyId = ref<number | null>(null);

const cancelOpen = ref(false);
const cancelTarget = ref<TourDateGlobalAdmin | null>(null);
const cancelForm = useForm({ reason: '' });

function openCancel(date: TourDateGlobalAdmin): void {
    cancelTarget.value = date;
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelOpen.value = true;
}

function confirmCancel(): void {
    const target = cancelTarget.value;

    if (target === null || cancelForm.processing) {
        return;
    }

    cancelForm
        .transform((data) => ({
            reason: data.reason.trim() === '' ? null : data.reason.trim(),
        }))
        .patch(CancelTourDateController(target.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('Salida inhabilitada.'));
                cancelOpen.value = false;
            },
            onError: () => {
                toast.error(t('No se pudo inhabilitar la salida.'));
            },
        });
}

const restoreForm = useForm({});

function restore(date: TourDateGlobalAdmin): void {
    if (busyId.value !== null) {
        return;
    }

    busyId.value = date.id;

    restoreForm.patch(RestoreTourDateController(date.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('Salida habilitada.'));
        },
        onError: () => {
            toast.error(t('No se pudo habilitar la salida.'));
        },
        onFinish: () => {
            busyId.value = null;
        },
    });
}

const assignOpen = ref(false);
const assignTarget = ref<TourDateGlobalAdmin | null>(null);
const assignForm = useForm<{ guide_id: number | null }>({ guide_id: null });

function openAssign(date: TourDateGlobalAdmin): void {
    assignTarget.value = date;
    assignForm.clearErrors();
    assignForm.guide_id = date.guide?.id ?? null;
    assignOpen.value = true;
}

function handleGuidePick(value: AcceptableValue): void {
    if (typeof value === 'string') {
        assignForm.guide_id = value === '' ? null : Number(value);
    }
}

function confirmAssign(): void {
    const target = assignTarget.value;

    if (target === null || assignForm.processing) {
        return;
    }

    if (assignForm.guide_id === null) {
        assignForm.setError('guide_id', t('Selecciona un guía.'));

        return;
    }

    assignForm.patch(AssignGuideController(target.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('Guía asignado.'));
            assignOpen.value = false;
        },
        onError: () => {
            toast.error(t('No se pudo asignar el guía.'));
        },
    });
}

const deleteOpen = ref(false);
const deleteTarget = ref<TourDateGlobalAdmin | null>(null);
const deleteForm = useForm({});

function openDelete(date: TourDateGlobalAdmin): void {
    deleteTarget.value = date;
    deleteOpen.value = true;
}

function confirmDelete(): void {
    const target = deleteTarget.value;

    if (target === null || deleteForm.processing) {
        return;
    }

    busyId.value = target.id;

    deleteForm.delete(destroyDeparture(target.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('Salida eliminada.'));
            deleteOpen.value = false;
        },
        onError: () => {
            toast.error(t('No se pudo eliminar la salida.'));
        },
        onFinish: () => {
            busyId.value = null;
        },
    });
}

function goToPage(page: number): void {
    if (page < 1 || page > meta.value.last_page) {
        return;
    }

    reload(page);
}

const guideSelectValue = computed(() =>
    assignForm.guide_id === null ? '' : String(assignForm.guide_id),
);
</script>

<template>
    <div>
        <Head :title="$t('Salidas')" />

        <div class="px-4 py-6 md:px-8">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <Heading
                    :title="$t('Salidas')"
                    :description="
                        $t(
                            'Cada salida es una fecha real con su cupo, su precio y su guía.',
                        )
                    "
                />
                <Button @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('Nueva salida') }}
                </Button>
            </div>

            <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    :label="$t('Salidas activas')"
                    :value="formatNumber(props.stats.active)"
                    :detail="$t('próximas y habilitadas')"
                />
                <KpiCard
                    :label="$t('Cupos por vender')"
                    :value="formatNumber(props.stats.seats_left)"
                    :detail="$t('en salidas futuras')"
                />
                <KpiCard
                    :label="$t('Viajeros confirmados')"
                    :value="formatNumber(props.stats.travellers)"
                    :detail="$t('con reserva activa')"
                />
                <KpiCard
                    :label="$t('Sin guía asignado')"
                    :value="formatNumber(props.stats.without_guide)"
                    :detail="$t('requieren asignación')"
                    :alert="props.stats.without_guide > 0"
                />
            </div>

            <DepartureBoardFilters
                v-model:search="search"
                v-model:scope="scope"
                v-model:tour-id="tourId"
                v-model:direction="direction"
                class="mt-5"
                :tabs="scopeTabs"
                :result-label="resultLabel"
                :tours="props.tours"
                :all-tours-value="ALL_TOURS"
            />

            <div class="mt-4 rounded-2xl border border-border bg-card">
                <div v-if="reloading" class="space-y-2 p-4">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="h-14 animate-pulse rounded-lg bg-muted"
                    />
                </div>

                <div
                    v-else-if="props.departures.data.length === 0"
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
                                          'Programa la primera con el botón Nueva salida.',
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

                <DepartureBoardTable
                    v-else
                    :departures="props.departures.data"
                    :totals="props.totals"
                    :busy-id="busyId"
                    @detail="openDetail"
                    @edit="openEdit"
                    @cancel="openCancel"
                    @restore="restore"
                    @assign-guide="openAssign"
                    @remove="openDelete"
                />

                <div
                    v-if="!reloading && meta.total > 0"
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
            v-if="selectedTour"
            v-model:open="dialogOpen"
            :tour-id="selectedTour.id"
            :editing="editing"
            :duration-hours="selectedTour.duration_hours"
            :departure-defaults="selectedTour.departure_defaults"
            :tour-routes="selectedTour.routes"
            :guides="props.departureOptions.guides"
            :providers="props.departureOptions.providers"
            :hotels="props.departureOptions.hotels"
        />

        <Dialog v-model:open="productPickerOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Nueva salida') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Elige el producto: la salida hereda su capacidad, su precio y su ruta.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="new-departure-tour">{{ $t('Producto') }}</Label>
                    <Select
                        :model-value="productPick"
                        @update:model-value="handleProductPick"
                    >
                        <SelectTrigger id="new-departure-tour" class="w-full">
                            <SelectValue
                                :placeholder="$t('Selecciona un producto')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="tour in props.tours"
                                    :key="tour.id"
                                    :value="String(tour.id)"
                                >
                                    {{ tour.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="productPickerOpen = false"
                    >
                        {{ $t('Volver') }}
                    </Button>
                    <Button
                        :disabled="productPick === ''"
                        @click="confirmProduct"
                    >
                        {{ $t('Continuar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="assignOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Asignar guía') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'El guía queda ocupado durante todos los días de la salida.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="assign-guide">{{ $t('Guía') }}</Label>
                    <Select
                        :model-value="guideSelectValue"
                        @update:model-value="handleGuidePick"
                    >
                        <SelectTrigger id="assign-guide" class="w-full">
                            <SelectValue
                                :placeholder="$t('Selecciona un guía')"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem
                                    v-for="guide in props.departureOptions
                                        .guides"
                                    :key="guide.id"
                                    :value="String(guide.id)"
                                >
                                    {{ guide.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="assignForm.errors.guide_id"
                        class="text-xs text-destructive"
                    >
                        {{ assignForm.errors.guide_id }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="assignForm.processing"
                        @click="assignOpen = false"
                    >
                        {{ $t('Volver') }}
                    </Button>
                    <Button
                        :disabled="assignForm.processing"
                        @click="confirmAssign"
                    >
                        <Loader2
                            v-if="assignForm.processing"
                            class="size-4 animate-spin"
                        />
                        {{ $t('Asignar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
                        v-model="cancelForm.reason"
                        rows="3"
                        :placeholder="$t('Ej: clima adverso')"
                    />
                    <p
                        v-if="cancelForm.errors.reason"
                        class="text-xs text-destructive"
                    >
                        {{ cancelForm.errors.reason }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="cancelForm.processing"
                        @click="cancelOpen = false"
                    >
                        {{ $t('Volver') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="cancelForm.processing"
                        @click="confirmCancel"
                    >
                        <Loader2
                            v-if="cancelForm.processing"
                            class="size-4 animate-spin"
                        />
                        {{ $t('Inhabilitar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ $t('Eliminar salida') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'La salida se borra definitivamente. Solo es posible mientras no tenga reservas.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="deleteOpen = false"
                    >
                        {{ $t('Volver') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDelete"
                    >
                        <Loader2
                            v-if="deleteForm.processing"
                            class="size-4 animate-spin"
                        />
                        {{ $t('Eliminar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
