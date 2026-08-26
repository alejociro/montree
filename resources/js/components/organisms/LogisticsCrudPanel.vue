<script setup lang="ts">
import {
    Building2,
    Loader2,
    MapPin,
    Plus,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import HotelController from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import ProviderController from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import RouteController from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { useTranslations } from '@/composables/useTranslations';
import type {
    LogisticsField,
    LogisticsResourceKind,
    LogisticsRow,
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
    fields: LogisticsField[];
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
    {
        new: string;
        edit: string;
        search: string;
        created: string;
        updated: string;
        deleted: string;
    }
> = {
    routes: {
        new: 'Nueva ruta',
        edit: 'Editar ruta',
        search: 'Buscar ruta',
        created: 'Ruta creada.',
        updated: 'Ruta actualizada.',
        deleted: 'Ruta eliminada.',
    },
    providers: {
        new: 'Nuevo proveedor',
        edit: 'Editar proveedor',
        search: 'Buscar proveedor',
        created: 'Proveedor creado.',
        updated: 'Proveedor actualizado.',
        deleted: 'Proveedor eliminado.',
    },
    hotels: {
        new: 'Nuevo hotel',
        edit: 'Editar hotel',
        search: 'Buscar hotel',
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

const rows = ref<LogisticsRow[]>([]);
const loading = ref(true);
const loadError = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const processing = ref(false);
const errors = ref<ApiErrors>({});
const form = reactive<Record<string, string>>({});

function blankForm(): void {
    for (const field of props.fields) {
        form[field.key] = '';
    }
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const term = props.search.trim();
        const url =
            term === ''
                ? controller.index().url
                : controller.index({ query: { search: term } }).url;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = (await response.json()) as { data: LogisticsRow[] };
        rows.value = json.data;
        emit('update:count', json.data.length);
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
            void load();
        }, 300);
    },
);

function openCreate(): void {
    editingId.value = null;
    errors.value = {};
    blankForm();
    dialogOpen.value = true;
}

function openEdit(row: LogisticsRow): void {
    editingId.value = row.id;
    errors.value = {};

    for (const field of props.fields) {
        const value = row[field.key];
        form[field.key] =
            value === null || value === undefined ? '' : String(value);
    }

    dialogOpen.value = true;
}

function buildPayload(): Record<string, string | null> {
    const payload: Record<string, string | null> = {};

    for (const field of props.fields) {
        const value = form[field.key]?.trim() ?? '';
        payload[field.key] = value === '' ? null : value;
    }

    return payload;
}

function submit(): void {
    if (processing.value) {
        return;
    }

    processing.value = true;
    errors.value = {};
    const payload = buildPayload();

    const options = {
        onSuccess: () => {
            toast.success(
                editingId.value === null
                    ? t(copy.value.created)
                    : t(copy.value.updated),
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

    if (editingId.value === null) {
        void api.post(controller.store().url, payload, options);

        return;
    }

    void api.put(controller.update(editingId.value).url, payload, options);
}

function remove(row: LogisticsRow): void {
    if (!confirm(t('¿Eliminar ":name"?', { name: row.name }))) {
        return;
    }

    void api.delete(controller.destroy(row.id).url, {
        onSuccess: () => {
            toast.success(t(copy.value.deleted));
            void load();
        },
        onError: (received) => {
            toast.error(received._global ?? t('No se pudo eliminar.'));
        },
    });
}

/**
 * Datos clave de la ficha, según el tipo. Solo se listan los campos que HOY
 * existen en la base: el handoff pedía además municipio, NIT, tarifas,
 * capacidad y vencimientos de póliza, y ninguno tiene columna todavía.
 * TODO(logística): ampliar el esquema de rutas, proveedores y hoteles.
 */
function factsOf(row: LogisticsRow): { label: string; value: string }[] {
    const facts: { label: string; value: string }[] = [];

    const push = (label: string, value: unknown): void => {
        if (value !== null && value !== undefined && String(value) !== '') {
            facts.push({ label, value: String(value) });
        }
    };

    if (props.kind === 'routes') {
        push(t('Distancia'), row.distance_km ? `${row.distance_km} km` : null);
        push(
            t('Duración'),
            row.duration_hours ? `${row.duration_hours} h` : null,
        );
    }

    if (props.kind === 'providers') {
        push(t('Servicio'), row.service_type);
        push(t('Contacto'), row.contact_name);
        push(t('Teléfono'), row.contact_phone);
    }

    if (props.kind === 'hotels') {
        push(t('Teléfono'), row.contact_phone);
        push(t('Correo'), row.contact_email);
    }

    return facts;
}

function subtitleOf(row: LogisticsRow): string | null {
    const value = row.address ?? row.service_type ?? null;

    return value === null || value === '' ? null : String(value);
}

function descriptionOf(row: LogisticsRow): string | null {
    const value = row.description ?? row.notes ?? null;

    return value === null || value === '' ? null : String(value);
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
                class="h-40 animate-pulse rounded-2xl bg-muted"
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
          —distancia, servicio, contacto— y no solo el nombre.
        -->
        <div v-else class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="row in rows"
                :key="row.id"
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
                            {{ row.name }}
                        </h3>
                        <p
                            v-if="subtitleOf(row)"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ subtitleOf(row) }}
                        </p>
                    </div>
                    <ActionMenu
                        variant="ghost"
                        :label="$t('Acciones de :name', { name: row.name })"
                    >
                        <DropdownMenuItem
                            variant="destructive"
                            @select="remove(row)"
                        >
                            <Trash2 class="size-4" />
                            {{ $t('Eliminar') }}
                        </DropdownMenuItem>
                    </ActionMenu>
                </div>

                <p
                    v-if="descriptionOf(row)"
                    class="mt-3 line-clamp-2 text-[13px] text-muted-foreground"
                >
                    {{ descriptionOf(row) }}
                </p>

                <dl
                    v-if="factsOf(row).length > 0"
                    class="mt-3 space-y-1.5 text-[13px]"
                >
                    <div
                        v-for="fact in factsOf(row)"
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
                    class="mt-4 flex items-center justify-between gap-2 border-t border-brand-line-2 pt-3"
                >
                    <MonoLabel>
                        {{
                            $tc(
                                'Usada en :count salida|Usada en :count salidas',
                                row.tour_dates_count,
                            )
                        }}
                    </MonoLabel>
                    <Button size="sm" variant="outline" @click="openEdit(row)">
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
                <span
                    v-if="rows.length === 0"
                    class="max-w-[32ch] text-xs text-muted-foreground"
                >
                    {{ emptyLabel }}.
                    {{
                        $t('Crea el primero para reutilizarlo en tus salidas.')
                    }}
                </span>
            </button>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingId === null ? newLabel : $t(copy.edit) }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ $t('Los campos marcados con * son obligatorios.') }}
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submit"
                >
                    <p
                        v-if="errors._global"
                        class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive sm:col-span-2"
                    >
                        {{ errors._global }}
                    </p>

                    <div
                        v-for="field in fields"
                        :key="field.key"
                        class="space-y-1.5"
                        :class="
                            field.type === 'textarea' || field.fullWidth
                                ? 'sm:col-span-2'
                                : ''
                        "
                    >
                        <Label :for="`field-${kind}-${field.key}`">
                            {{ field.label }}{{ field.required ? ' *' : '' }}
                        </Label>
                        <Textarea
                            v-if="field.type === 'textarea'"
                            :id="`field-${kind}-${field.key}`"
                            v-model="form[field.key]"
                            rows="3"
                            :placeholder="field.placeholder"
                        />
                        <Input
                            v-else
                            :id="`field-${kind}-${field.key}`"
                            v-model="form[field.key]"
                            :type="field.type"
                            :placeholder="field.placeholder"
                        />
                        <p
                            v-if="errors[field.key]"
                            class="text-xs text-destructive"
                        >
                            {{ errors[field.key] }}
                        </p>
                    </div>

                    <DialogFooter class="sm:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="processing"
                            @click="dialogOpen = false"
                        >
                            {{ $t('Cancelar') }}
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Loader2
                                v-if="processing"
                                class="size-4 animate-spin"
                            />
                            {{
                                editingId === null ? $t('Crear') : $t('Guardar')
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
