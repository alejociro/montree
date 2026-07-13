<script setup lang="ts">
import { Loader2, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import HotelController from '@/actions/App/Http/Controllers/Api/V1/Admin/HotelController';
import ProviderController from '@/actions/App/Http/Controllers/Api/V1/Admin/ProviderController';
import RouteController from '@/actions/App/Http/Controllers/Api/V1/Admin/RouteController';
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
import { Textarea } from '@/components/ui/textarea';
import { useApi  } from '@/composables/useApi';
import type {ApiErrors} from '@/composables/useApi';
import type {
    LogisticsField,
    LogisticsResourceKind,
    LogisticsRow,
} from '@/types/logistics';

type CrudController = {
    index: (options?: { query?: Record<string, string> }) => { url: string };
    store: () => { url: string };
    update: (id: number) => { url: string };
    destroy: (id: number) => { url: string };
};

type Props = {
    kind: LogisticsResourceKind;
    singular: string;
    feminine?: boolean;
    fields: LogisticsField[];
    emptyLabel: string;
};

const props = defineProps<Props>();

const newLabel = computed(
    () => `${props.feminine ? 'Nueva' : 'Nuevo'} ${props.singular.toLowerCase()}`,
);
const pastSuffix = computed(() => (props.feminine ? 'a' : 'o'));

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
const search = ref('');
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
        const url =
            search.value.trim() === ''
                ? controller.index().url
                : controller.index({ query: { search: search.value.trim() } })
                      .url;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const json = (await response.json()) as { data: LogisticsRow[] };
        rows.value = json.data;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        void load();
    }, 300);
});

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
        form[field.key] = value === null || value === undefined ? '' : String(value);
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
                    ? `${props.singular} cread${pastSuffix.value}.`
                    : `${props.singular} actualizad${pastSuffix.value}.`,
            );
            dialogOpen.value = false;
            void load();
        },
        onError: (received: ApiErrors) => {
            errors.value = received;
            toast.error(received._global ?? 'Revisá los campos marcados.');
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
    if (!confirm(`¿Eliminar "${row.name}"?`)) {
        return;
    }

    void api.delete(controller.destroy(row.id).url, {
        onSuccess: () => {
            toast.success(`${props.singular} eliminad${pastSuffix.value}.`);
            void load();
        },
        onError: (received) => {
            toast.error(received._global ?? 'No se pudo eliminar.');
        },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative w-full max-w-xs">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Buscar por nombre"
                    class="pl-9"
                    :aria-label="`Buscar ${singular}`"
                />
            </div>
            <Button size="sm" @click="openCreate">
                <Plus class="size-4" />
                {{ newLabel }}
            </Button>
        </div>

        <div v-if="loading" class="space-y-2">
            <div
                v-for="n in 3"
                :key="n"
                class="h-14 animate-pulse rounded-lg bg-muted"
            />
        </div>

        <div
            v-else-if="loadError"
            class="rounded-lg border border-destructive/40 bg-destructive/5 p-6 text-center"
        >
            <p class="text-sm text-destructive">
                No se pudo cargar el catálogo.
            </p>
            <Button variant="outline" size="sm" class="mt-3" @click="load">
                Reintentar
            </Button>
        </div>

        <div
            v-else-if="rows.length === 0"
            class="rounded-lg border border-dashed border-border p-8 text-center"
        >
            <p class="font-medium text-foreground">{{ emptyLabel }}</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Creá el primero para reutilizarlo en tus salidas.
            </p>
        </div>

        <ul v-else class="space-y-2">
            <li
                v-for="row in rows"
                :key="row.id"
                class="flex items-center justify-between gap-3 rounded-lg border border-border bg-background p-3"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-medium text-foreground">
                            {{ row.name }}
                        </p>
                        <Badge variant="outline">
                            {{ row.tour_dates_count }} salida(s)
                        </Badge>
                    </div>
                    <p
                        v-if="row.description || row.service_type || row.address"
                        class="truncate text-sm text-muted-foreground"
                    >
                        {{ row.description ?? row.service_type ?? row.address }}
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Editar"
                        @click="openEdit(row)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        title="Eliminar"
                        @click="remove(row)"
                    >
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </div>
            </li>
        </ul>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingId === null
                                ? newLabel
                                : `Editar ${singular.toLowerCase()}`
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Los campos marcados con * son obligatorios.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
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
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="processing">
                            <Loader2
                                v-if="processing"
                                class="size-4 animate-spin"
                            />
                            {{ editingId === null ? 'Crear' : 'Guardar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
