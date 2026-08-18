<script setup lang="ts">
import { Lock } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    show as showRole,
    store as storeRole,
    update as updateRole,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import PermissionPicker from '@/components/organisms/PermissionPicker.vue';
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
import { useApi } from '@/composables/useApi';
import type { ApiErrors } from '@/composables/useApi';
import { PERMISSION_CATALOG } from '@/config/permissions';
import type { PermissionSummary, RoleDetail } from '@/types/role';

type Mode = 'create' | 'edit' | 'view';

type Props = {
    open: boolean;
    /** `null` en modo creación. */
    roleId: number | null;
    mode: Mode;
    /**
     * Universo de permisos marcables. Lo manda el backend con el listado de
     * roles (`meta.available_permissions`); vacío = se usa el espejo local.
     */
    catalog?: PermissionSummary[];
};

const props = withDefaults(defineProps<Props>(), { catalog: () => [] });

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const api = useApi();

/** Espejo de `StoreRoleRequest`/`UpdateRoleRequest`: `max:60`. */
const NAME_MAX_LENGTH = 60;

const detail = ref<RoleDetail | null>(null);
const loading = ref(false);
const loadError = ref(false);
const saving = ref(false);
const errors = ref<ApiErrors>({});
const submitted = ref(false);

const name = ref('');
const permissions = ref<string[]>([]);

const isReadonly = computed(
    () => props.mode === 'view' || detail.value?.is_base === true,
);

const title = computed(() => {
    if (props.mode === 'create') {
        return 'Crear rol';
    }

    return isReadonly.value ? 'Permisos del rol' : 'Editar rol';
});

const description = computed(() => {
    if (props.mode === 'create') {
        return 'Elige un nombre y marca los permisos que tendrá este rol dentro de tu agencia.';
    }

    return isReadonly.value
        ? 'Los roles del sistema son iguales para todas las agencias, por eso no se pueden editar. Crea un rol propio si necesitas otra combinación de permisos.'
        : 'Cambia el nombre o los permisos de este rol. Afecta de inmediato a todos los miembros que lo tengan.';
});

/**
 * El detalle solo trae los permisos que el rol YA tiene; el universo marcable es
 * el catálogo completo, que manda el backend y que el espejo local
 * (`config/permissions.ts`) solo respalda.
 */
const catalog = computed(() =>
    props.catalog.length > 0 ? props.catalog : PERMISSION_CATALOG,
);

const nameError = computed(() => {
    if (errors.value.name) {
        return errors.value.name;
    }

    if (!submitted.value) {
        return '';
    }

    if (name.value.trim() === '') {
        return 'El nombre del rol es obligatorio.';
    }

    if (name.value.trim().length > NAME_MAX_LENGTH) {
        return `El nombre no puede superar los ${NAME_MAX_LENGTH} caracteres.`;
    }

    return '';
});

const permissionsError = computed(() => {
    if (errors.value.permissions) {
        return errors.value.permissions;
    }

    return submitted.value && permissions.value.length === 0
        ? 'Elige al menos un permiso.'
        : '';
});

const canSubmit = computed(
    () =>
        !saving.value &&
        !loading.value &&
        name.value.trim() !== '' &&
        name.value.trim().length <= NAME_MAX_LENGTH &&
        permissions.value.length > 0,
);

function reset(): void {
    detail.value = null;
    errors.value = {};
    submitted.value = false;
    loadError.value = false;
    name.value = '';
    permissions.value = [];
}

async function loadDetail(roleId: number): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetch(showRole.url(roleId), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const json = (await response.json()) as
            | RoleDetail
            | { data: RoleDetail };
        const role = 'data' in json ? json.data : json;

        detail.value = role;
        name.value = role.label !== '' ? role.label : role.name;
        permissions.value = role.permissions.map(
            (permission) => permission.slug,
        );
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    submitted.value = true;

    if (!canSubmit.value || isReadonly.value) {
        return;
    }

    saving.value = true;
    errors.value = {};

    const payload = {
        name: name.value.trim(),
        permissions: permissions.value,
    };

    const options = {
        onSuccess: () => {
            emit('saved');
            close();
        },
        onError: (received: ApiErrors) => {
            errors.value = received;
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    void (props.roleId === null
        ? api.post(storeRole().url, payload, options)
        : api.patch(updateRole.url(props.roleId), payload, options));
}

watch(
    () => [props.open, props.roleId] as const,
    ([open, roleId]) => {
        if (!open) {
            return;
        }

        reset();

        if (roleId !== null) {
            void loadDetail(roleId);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(value: boolean) => emit('update:open', value)"
    >
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    {{ title }}
                    <Badge
                        v-if="detail?.is_base"
                        variant="secondary"
                        class="gap-1 font-normal"
                    >
                        <Lock class="size-3" />
                        Solo lectura
                    </Badge>
                </DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <!-- Loading -->
            <div v-if="loading" class="space-y-3">
                <div class="h-10 animate-pulse rounded-md bg-muted" />
                <div class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="h-28 animate-pulse rounded-xl bg-muted"
                    />
                </div>
            </div>

            <!-- Error -->
            <div v-else-if="loadError" class="py-8 text-center">
                <p class="text-sm text-destructive">
                    No se pudieron cargar los permisos de este rol.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    class="mt-3"
                    @click="roleId !== null && loadDetail(roleId)"
                >
                    Reintentar
                </Button>
            </div>

            <form v-else class="space-y-5" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="role-name">Nombre del rol</Label>
                    <Input
                        id="role-name"
                        v-model="name"
                        :disabled="isReadonly"
                        :maxlength="NAME_MAX_LENGTH"
                        :aria-invalid="nameError !== ''"
                        :aria-describedby="
                            nameError !== '' ? 'role-name-error' : undefined
                        "
                        placeholder="Ventas fin de semana"
                        autocomplete="off"
                    />
                    <p
                        v-if="nameError !== ''"
                        id="role-name-error"
                        class="text-xs text-destructive"
                    >
                        {{ nameError }}
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">Permisos</p>
                    <PermissionPicker
                        v-model="permissions"
                        :catalog="catalog"
                        :disabled="isReadonly"
                    />
                    <p
                        v-if="permissionsError !== ''"
                        class="text-xs text-destructive"
                    >
                        {{ permissionsError }}
                    </p>
                </div>

                <p v-if="errors._global" class="text-sm text-destructive">
                    {{ errors._global }}
                </p>
            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="close">
                    {{ isReadonly ? 'Cerrar' : 'Cancelar' }}
                </Button>
                <Button
                    v-if="!isReadonly && !loadError"
                    type="button"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ saving ? 'Guardando...' : 'Guardar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
