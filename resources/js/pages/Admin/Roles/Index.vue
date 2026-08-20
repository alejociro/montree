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
import { useTranslations } from '@/composables/useTranslations';
import type {
    PermissionSummary,
    RoleListItem,
    RoleListResponse,
} from '@/types/role';

const { t } = useTranslations();

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
    toast.success(t('Rol guardado'));
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
            toast.success(t('Rol ":role" eliminado', { role: role.label }));
            deleteTarget.value = null;
            void load();
        },
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ?? t('No se pudo eliminar el rol.'),
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
        <Head :title="$t('Roles y permisos')" />

        <div class="px-4 py-6 md:px-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="$t('Roles y permisos')"
                    :description="
                        $t(
                            'Qué puede hacer cada rol dentro del panel. Los roles del sistema son iguales para todas las agencias; los propios los defines tú.',
                        )
                    "
                />
                <Button v-if="canManage" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('Crear rol') }}
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
                    {{ $t('No se pudieron cargar los roles.') }}
                </p>
                <Button variant="outline" size="sm" class="mt-3" @click="load">
                    {{ $t('Reintentar') }}
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
                                {{ $t('Roles del sistema') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'Vienen con MONTREE. Puedes ver sus permisos pero no modificarlos.',
                                    )
                                }}
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
                                            {{ $t('Solo lectura') }}
                                        </Badge>
                                    </div>
                                    <p
                                        class="text-sm text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            $tc(
                                                ':count permiso|:count permisos',
                                                role.permissions_count,
                                            )
                                        }}
                                        ·
                                        {{
                                            $tc(
                                                ':count miembro|:count miembros',
                                                role.users_count,
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openRole(role)"
                                >
                                    {{ $t('Ver permisos') }}
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
                                {{ $t('Roles propios de la agencia') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'Combinaciones de permisos que creas tú. Solo existen dentro de tu agencia.',
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            v-if="ownRoles.length === 0"
                            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border p-10 text-center"
                        >
                            <p class="font-medium">
                                {{ $t('Todavía no hay roles propios') }}
                            </p>
                            <p class="max-w-md text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'Crea uno cuando necesites una combinación de permisos que los roles del sistema no cubren.',
                                    )
                                }}
                            </p>
                            <Button
                                v-if="canManage"
                                variant="outline"
                                size="sm"
                                @click="openCreate"
                            >
                                <Plus class="size-4" />
                                {{ $t('Crear rol') }}
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
                                        {{
                                            $tc(
                                                ':count permiso|:count permisos',
                                                role.permissions_count,
                                            )
                                        }}
                                        ·
                                        {{
                                            $tc(
                                                ':count miembro|:count miembros',
                                                role.users_count,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openRole(role)"
                                    >
                                        {{
                                            canManage ? $t('Editar') : $t('Ver')
                                        }}
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
                                                    {{ $t('Eliminar') }}
                                                </Button>
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{
                                                $tc(
                                                    'Tiene :count miembro asignado. Quítaselo desde Equipo antes de eliminarlo.|Tiene :count miembros asignados. Quítaselos desde Equipo antes de eliminarlo.',
                                                    role.users_count,
                                                )
                                            }}
                                        </TooltipContent>
                                    </Tooltip>
                                    <Button
                                        v-else-if="canManage"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(role)"
                                    >
                                        {{ $t('Eliminar') }}
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
                    <DialogTitle>{{ $t('Eliminar rol') }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                '¿Eliminar el rol ":role"? Esta acción no se puede deshacer.',
                                { role: deleteTarget?.label ?? '' },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteTarget = null">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="destroyRole"
                    >
                        {{ deleting ? $t('Eliminando...') : $t('Eliminar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
