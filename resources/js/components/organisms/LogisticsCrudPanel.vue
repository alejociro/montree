<script setup lang="ts">
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import HotelController from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import ProviderController from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import RouteController from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import LogisticsRecordDialog from '@/components/organisms/LogisticsRecordDialog.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import { factsFor, localityOf } from '@/lib/logistics';
import type {
    LogisticsRecord,
    LogisticsResourceKind,
    PaginationMeta,
} from '@/types/logistics';

const { t } = useTranslations();

type CrudController = {
    index: (options?: { query?: Record<string, string> }) => { url: string };
    store: () => { url: string };
    update: (id: number) => { url: string };
    destroy: (id: number) => { url: string };
};

type Props = {
    kind: LogisticsResourceKind;
    emptyLabel: string;
    /**
     * El buscador vive en la barra de filtros de la página, encima de las
     * pestañas: es uno solo para los tres catálogos, como pide el sistema de
     * diseño. El panel solo lo consume.
     */
    search?: string;
};

const props = withDefaults(defineProps<Props>(), { search: '' });

const emit = defineEmits<{
    (e: 'update:count', value: number): void;
}>();

/** Icono de la ficha, por tipo de recurso. */
const KIND_ICONS = {
    routes: MapPin,
    providers: Truck,
    hotels: Building2,
} as const;

const icon = computed(() => KIND_ICONS[props.kind]);

/**
 * Copy completo por recurso en vez de armarlo con `singular` + genero.
 *
 * WHY: "Nueva" + "ruta" y el sufijo `eliminad{a|o}` son gramatica del castellano;
 * al traducir salia "Nueva route". Cada frase entera es una clave y el ingles la
 * resuelve sin depender del genero.
 */
const KIND_COPY: Record<
    LogisticsResourceKind,
    { new: string; created: string; updated: string; deleted: string }
> = {
    routes: {
        new: 'Nueva ruta',
        created: 'Ruta creada.',
        updated: 'Ruta actualizada.',
        deleted: 'Ruta eliminada.',
    },
    providers: {
        new: 'Nuevo proveedor',
        created: 'Proveedor creado.',
        updated: 'Proveedor actualizado.',
        deleted: 'Proveedor eliminado.',
    },
    hotels: {
        new: 'Nuevo hotel',
        created: 'Hotel creado.',
        updated: 'Hotel actualizado.',
        deleted: 'Hotel eliminado.',
    },
};

const copy = computed(() => KIND_COPY[props.kind]);
const newLabel = computed(() => t(copy.value.new));

const api = useApi();

const controllers: Record<LogisticsResourceKind, CrudController> = {
    routes: RouteController,
    providers: ProviderController,
    hotels: HotelController,
};

const controller = controllers[props.kind];

const records = ref<LogisticsRecord[]>([]);
const meta = ref<PaginationMeta | null>(null);
const loading = ref(true);
const loadError = ref(false);
const page = ref(1);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const dialogOpen = ref(false);
const editing = ref<LogisticsRecord | null>(null);
const processing = ref(false);
const errors = ref<ApiErrors>({});

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const query: Record<string, string> = { page: String(page.value) };
        const term = props.search.trim();

        if (term !== '') {
            query.search = term;
        }

        const response = await fetch(controller.index({ query }).url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = (await response.json()) as {
            data: LogisticsRecord[];
            meta: PaginationMeta;
        };
        records.value = json.data;
        meta.value = json.meta;
        emit('update:count', json.meta?.total ?? json.data.length);
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.search,
    () => {
        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(() => {
            // Un término nuevo empieza en la primera página: si no, buscar
            // desde la página 3 devolvía una rejilla vacía.
            page.value = 1;
            void load();
        }, 300);
    },
);

function goToPage(next: number): void {
    if (meta.value === null || next < 1 || next > meta.value.last_page) {
        return;
    }

    page.value = next;
    void load();
}

function openCreate(): void {
    editing.value = null;
    errors.value = {};
    dialogOpen.value = true;
}

function openEdit(record: LogisticsRecord): void {
    editing.value = record;
    errors.value = {};
    dialogOpen.value = true;
}

function submit(payload: Record<string, unknown>): void {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};
    const record = editing.value;

    const options = {
        onSuccess: () => {
            toast.success(
                record === null ? t(copy.value.created) : t(copy.value.updated),
            );
            dialogOpen.value = false;
            void load();
        },
        onError: (received: ApiErrors) => {
            errors.value = received;
            toast.error(received._global ?? t('Revisa los campos marcados.'));
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (record === null) {
        void api.post(controller.store().url, payload, options);

        return;
    }

    void api.put(controller.update(record.id).url, payload, options);
}

function remove(record: LogisticsRecord): void {
    if (!confirm(t('¿Eliminar ":name"?', { name: record.name }))) {
        return;
    }

    void api.delete(controller.destroy(record.id).url, {
        onSuccess: () => {
            toast.success(t(copy.value.deleted));
            void load();
        },
        onError: (received) => {
            toast.error(received._global ?? t('No se pudo eliminar.'));
        },
    });
}

function descriptionOf(record: LogisticsRecord): string | null {
    const source = record as unknown as Record<string, unknown>;
    const value = source.description ?? source.notes;

    return typeof value === 'string' && value !== '' ? value : null;
}

defineExpose({ openCreate });

onMounted(load);
</script>

<template>
    <div>
        <div v-if="loading" class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="n in 3"
                :key="n"
                class="h-52 animate-pulse rounded-2xl bg-muted"
            />
        </div>

        <div
            v-else-if="loadError"
            class="rounded-2xl border border-destructive/40 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">
                {{ $t('No se pudo cargar el catálogo.') }}
            </p>
            <Button variant="outline" size="sm" class="mt-3" @click="load">
                {{ $t('Reintentar') }}
            </Button>
        </div>

        <!--
          Fichas en rejilla, no filas: cada una tiene que decir algo operativo
          —distancia, NIT, tarifa, vencimiento— y no solo el nombre.
        -->
        <div v-else class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="record in records"
                :key="record.id"
                class="flex flex-col rounded-2xl border border-border bg-card p-4"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-[38px] shrink-0 place-items-center rounded-xl bg-primary-soft text-primary-readable"
                    >
                        <component :is="icon" class="size-4.5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-[15px] font-semibold">
                            {{ record.name }}
                        </h3>
                        <p
                            v-if="localityOf(record)"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ localityOf(record) }}
                        </p>
                    </div>
                    <ActionMenu
                        variant="ghost"
                        :label="$t('Acciones de :name', { name: record.name })"
                    >
                        <DropdownMenuItem @select="openEdit(record)">
                            <Pencil class="size-4" />
                            {{ $t('Editar ficha') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            variant="destructive"
                            @select="remove(record)"
                        >
                            <Trash2 class="size-4" />
                            {{ $t('Eliminar') }}
                        </DropdownMenuItem>
                    </ActionMenu>
                </div>

                <p
                    v-if="descriptionOf(record)"
                    class="mt-3 line-clamp-2 text-[13px] text-muted-foreground"
                >
                    {{ descriptionOf(record) }}
                </p>

                <dl
                    v-if="factsFor(props.kind, record).length > 0"
                    class="mt-3 mb-4 space-y-1.5 text-[13px]"
                >
                    <div
                        v-for="fact in factsFor(props.kind, record)"
                        :key="fact.label"
                        class="flex items-baseline justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">{{ fact.label }}</dt>
                        <dd class="truncate text-right font-medium">
                            {{ fact.value }}
                        </dd>
                    </div>
                </dl>

                <div
                    class="mt-auto flex items-center justify-between gap-2 border-t border-brand-line-2 pt-3.5"
                >
                    <MonoLabel>
                        {{
                            $tc(
                                'Usada en :count salida|Usada en :count salidas',
                                record.tour_dates_count,
                            )
                        }}
                    </MonoLabel>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="openEdit(record)"
                    >
                        <Pencil class="size-4" />
                        {{ $t('Editar') }}
                    </Button>
                </div>
            </article>

            <!-- Card punteada para crear, al final de la rejilla. -->
            <button
                type="button"
                class="flex min-h-[160px] flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-input p-6 text-center transition hover:border-primary hover:bg-primary-soft/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openCreate"
            >
                <Plus class="size-5 text-muted-foreground" />
                <span class="text-sm font-medium">{{ newLabel }}</span>
                <span class="max-w-[34ch] text-xs text-muted-foreground">
                    <template v-if="records.length === 0">
                        {{ emptyLabel }}.
                    </template>
                    {{
                        $t(
                            'Guarda la ficha completa una vez y reutilízala en cada salida.',
                        )
                    }}
                </span>
            </button>
        </div>

        <!--
          Paginación: los catálogos crecen sin techo y la rejilla solo trae 12
          fichas por página. Sin esto, la ficha 13 no existía para el usuario.
        -->
        <div
            v-if="meta && meta.last_page > 1"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card px-4 py-3 text-[13px]"
        >
            <span class="text-muted-foreground">
                {{
                    $t('Mostrando :from–:to de :total fichas', {
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

        <LogisticsRecordDialog
            v-model:open="dialogOpen"
            :kind="props.kind"
            :record="editing"
            :processing="processing"
            :errors="errors"
            @submit="submit"
        />
    </div>
</template>
