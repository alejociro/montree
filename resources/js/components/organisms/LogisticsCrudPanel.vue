<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import HotelController from '@/actions/App/Http/Controllers/Admin/HotelController';
import { index as logisticsIndex } from '@/actions/App/Http/Controllers/Admin/LogisticsPagesController';
import ProviderController from '@/actions/App/Http/Controllers/Admin/ProviderController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import LogisticsRecordDialog from '@/components/organisms/LogisticsRecordDialog.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/composables/useTranslations';
import { factsFor, localityOf } from '@/lib/logistics';
import type {
    LogisticsCatalogKind,
    LogisticsRecord,
    PaginationMeta,
} from '@/types/logistics';

const { t } = useTranslations();

const page = usePage();

type CrudRoutes = {
    store: () => { url: string };
    update: (id: number) => { url: string };
    destroy: (id: number) => { url: string };
};

type Props = {
    kind: LogisticsCatalogKind;
    /** La página trae los dos catálogos: el panel solo pinta el suyo. */
    records: LogisticsRecord[];
    meta: PaginationMeta;
    /** Página propia de este catálogo: los dos viajan juntos y paginan aparte. */
    pageName: 'providers_page' | 'hotels_page';
    emptyLabel: string;
};

const props = defineProps<Props>();

/** Icono de la ficha, por tipo de recurso. */
const KIND_ICONS = {
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
    LogisticsCatalogKind,
    { new: string; created: string; updated: string; deleted: string }
> = {
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

/**
 * Una ficha en uso no se borra: el servidor vuelve con el motivo bajo esta
 * clave —quién la usa— y aquí se cuenta como aviso, no como campo en rojo.
 */
const BLOCKED_ERROR_KEYS: Record<LogisticsCatalogKind, string> = {
    providers: 'provider',
    hotels: 'hotel',
};

const copy = computed(() => KIND_COPY[props.kind]);
const newLabel = computed(() => t(copy.value.new));

const controllers: Record<LogisticsCatalogKind, CrudRoutes> = {
    providers: ProviderController,
    hotels: HotelController,
};

const controller = controllers[props.kind];

const dialogOpen = ref(false);
const editing = ref<LogisticsRecord | null>(null);

const dialogAction = computed<{ url: string; method: 'post' | 'put' }>(() => {
    const record = editing.value;

    return record === null
        ? { url: controller.store().url, method: 'post' }
        : { url: controller.update(record.id).url, method: 'put' };
});

function goToPage(next: number): void {
    if (next < 1 || next > props.meta.last_page) {
        return;
    }

    router.get(
        logisticsIndex.url({ mergeQuery: { [props.pageName]: next } }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: [props.kind],
        },
    );
}

function openCreate(): void {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(record: LogisticsRecord): void {
    editing.value = record;
    dialogOpen.value = true;
}

function onSaved(): void {
    const created = editing.value === null;

    dialogOpen.value = false;
    editing.value = null;

    toast.success(
        page.props.flash.success ??
            t(created ? copy.value.created : copy.value.updated),
    );
}

function remove(record: LogisticsRecord): void {
    if (!confirm(t('¿Eliminar ":name"?', { name: record.name }))) {
        return;
    }

    router.delete(controller.destroy(record.id).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            toast.success(page.props.flash.success ?? t(copy.value.deleted));
        },
        onError: (errors) => {
            toast.error(
                errors[BLOCKED_ERROR_KEYS[props.kind]] ||
                    t('No se pudo eliminar.'),
            );
        },
    });
}

function descriptionOf(record: LogisticsRecord): string | null {
    const source = record as unknown as Record<string, unknown>;
    const value = source.description ?? source.notes;

    return typeof value === 'string' && value !== '' ? value : null;
}

defineExpose({ openCreate });
</script>

<template>
    <div>
        <!--
          Fichas en rejilla, no filas: cada una tiene que decir algo operativo
          —distancia, NIT, tarifa, vencimiento— y no solo el nombre.
        -->
        <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
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
            v-if="meta.last_page > 1"
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
            :action="dialogAction"
            @saved="onSaved"
        />
    </div>
</template>
