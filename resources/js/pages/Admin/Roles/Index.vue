<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Lock, Plus, ShieldCheck, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexRoles,
    destroy as destroyRoleRoute,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import Heading from '@/components/Heading.vue';
import RoleFormDialog from '@/components/organisms/RoleFormDialog.vue';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useApi } from '@/composables/useApi';
import { usePermissions } from '@/composables/usePermissions';
import type {
    PermissionSummary,
    RoleListItem,
    RoleListResponse,
} from '@/types/role';

const api = useApi();
const { can } = usePermissions();

/** El backend gobierna la pantalla entera con este permiso; acá solo se oculta. */
const canManage = computed(() => can('team.role.update'));

const roles = ref<RoleListItem[]>([]);
/** Catálogo completo de permisos, tal como lo manda el listado. */
const catalog = ref<PermissionSummary[]>([]);
const loading = ref(true);
const loadError = ref(false);

const baseRoles = computed(() => roles.value.filter((role) => role.is_base));
const ownRoles = computed(() => roles.value.filter((role) => !role.is_base));

const dialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const dialogRoleId = ref<number | null>(null);

const deleteTarget = ref<RoleListItem | null>(null);
const deleting = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetch(indexRoles().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const json = (await response.json()) as RoleListResponse;

        roles.value = json.data ?? [];
        catalog.value = json.meta?.available_permissions ?? [];
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

function openCreate(): void {
    dialogMode.value = 'create';
    dialogRoleId.value = null;
    dialogOpen.value = true;
}

function openRole(role: RoleListItem): void {
    dialogMode.value = role.is_base || !canManage.value ? 'view' : 'edit';
    dialogRoleId.value = role.id;
    dialogOpen.value = true;
}

function onSaved(): void {
    toast.success('Rol guardado');
    void load();
}

function confirmDelete(role: RoleListItem): void {
    deleteTarget.value = role;
}

function destroyRole(): void {
    const role = deleteTarget.value;

    if (!role) {
        return;
    }

    deleting.value = true;
    void api.delete(destroyRoleRoute.url(role.id), {
        onSuccess: () => {
            toast.success(`Rol "${role.label}" eliminado`);
            deleteTarget.value = null;
            void load();
        },
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ?? 'No se pudo eliminar el rol.',
            );
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

onMounted(load);
</script>

<template>
    <div>
        <Head title="Roles y permisos" />

        <div class="px-4 py-6 md:px-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    title="Roles y permisos"
                    description="Qué puede hacer cada rol dentro del panel. Los roles del sistema son iguales para todas las agencias; los propios los defines tú."
                />
                <Button v-if="canManage" @click="openCreate">
                    <Plus class="size-4" />
                    Crear rol
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="mt-6 space-y-3">
                <div
                    v-for="n in 4"
                    :key="n"
                    class="h-20 animate-pulse rounded-2xl bg-muted"
                />
            </div>

            <!-- Error -->
            <div
                v-else-if="loadError"
                class="mt-6 rounded-2xl border border-border p-10 text-center"
            >
                <p class="text-sm text-destructive">
                    No se pudieron cargar los roles.
                </p>
                <Button variant="outline" size="sm" class="mt-3" @click="load">
                    Reintentar
                </Button>
            </div>

            <TooltipProvider v-else>
                <div class="mt-6 space-y-8">
                    <!-- Roles del sistema -->
                    <section class="space-y-3">
                        <div class="space-y-1">
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <ShieldCheck
                                    class="size-4 text-muted-foreground"
                                />
                                Roles del sistema
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Vienen con MONTREE. Puedes ver sus permisos pero
                                no modificarlos.
                            </p>
                        </div>

                        <ul class="grid gap-3 md:grid-cols-2">
                            <li
                                v-for="role in baseRoles"
                                :key="role.id"
                                class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="min-w-0 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">
                                            {{ role.label }}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            class="gap-1 font-normal"
                                        >
                                            <Lock class="size-3" />
                                            Solo lectura
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{ role.permissions_count }} permisos ·
                                        {{ role.users_count }}
                                        {{
                                            role.users_count === 1
                                                ? 'miembro'
                                                : 'miembros'
                                        }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openRole(role)"
                                >
                                    Ver permisos
                                </Button>
                            </li>
                        </ul>
                    </section>

                    <!-- Roles propios -->
                    <section class="space-y-3">
                        <div class="space-y-1">
                            <h2
                                class="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Users class="size-4 text-muted-foreground" />
                                Roles propios de la agencia
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                Combinaciones de permisos que creas tú. Solo
                                existen dentro de tu agencia.
                            </p>
                        </div>

                        <div
                            v-if="ownRoles.length === 0"
                            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border p-10 text-center"
                        >
                            <p class="font-medium">
                                Todavía no hay roles propios
                            </p>
                            <p class="max-w-md text-sm text-muted-foreground">
                                Crea uno cuando necesites una combinación de
                                permisos que los roles del sistema no cubren.
                            </p>
                            <Button
                                v-if="canManage"
                                variant="outline"
                                size="sm"
                                @click="openCreate"
                            >
                                <Plus class="size-4" />
                                Crear rol
                            </Button>
                        </div>

                        <ul v-else class="grid gap-3 md:grid-cols-2">
                            <li
                                v-for="role in ownRoles"
                                :key="role.id"
                                class="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="min-w-0 space-y-1">
                                    <span class="block font-medium">
                                        {{ role.label }}
                                    </span>
                                    <p
                                        class="text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{ role.permissions_count }} permisos ·
                                        {{ role.users_count }}
                                        {{
                                            role.users_count === 1
                                                ? 'miembro'
                                                : 'miembros'
                                        }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openRole(role)"
                                    >
                                        {{ canManage ? 'Editar' : 'Ver' }}
                                    </Button>

                                    <Tooltip
                                        v-if="canManage && role.users_count > 0"
                                    >
                                        <TooltipTrigger as-child>
                                            <span
                                                tabindex="0"
                                                class="inline-flex"
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled
                                                    class="pointer-events-none text-muted-foreground"
                                                >
                                                    Eliminar
                                                </Button>
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Tiene {{ role.users_count }}
                                            {{
                                                role.users_count === 1
                                                    ? 'miembro asignado'
                                                    : 'miembros asignados'
                                            }}. Quítaselo desde Equipo antes de
                                            eliminarlo.
                                        </TooltipContent>
                                    </Tooltip>
                                    <Button
                                        v-else-if="canManage"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(role)"
                                    >
                                        Eliminar
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </section>
                </div>
            </TooltipProvider>
        </div>

        <RoleFormDialog
            v-model:open="dialogOpen"
            :role-id="dialogRoleId"
            :mode="dialogMode"
            :catalog="catalog"
            @saved="onSaved"
        />

        <Dialog
            :open="deleteTarget !== null"
            @update:open="(value: boolean) => !value && (deleteTarget = null)"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar rol</DialogTitle>
                    <DialogDescription>
                        ¿Eliminar el rol "{{ deleteTarget?.label }}"? Esta
                        acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="destroyRole"
                    >
                        {{ deleting ? 'Eliminando...' : 'Eliminar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
