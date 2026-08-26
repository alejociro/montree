<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Eye, Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    index as indexRoles,
    destroy as destroyRoleRoute,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import { index as indexUsers } from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ScopeBar from '@/components/atoms/ScopeBar.vue';
import Heading from '@/components/Heading.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import RoleFormDialog from '@/components/organisms/RoleFormDialog.vue';
import type { RoleSeed } from '@/components/organisms/RoleFormDialog.vue';
import RolePermissionsSheet from '@/components/organisms/RolePermissionsSheet.vue';
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
import { Skeleton } from '@/components/ui/skeleton';
import { useApi } from '@/composables/useApi';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatNumber } from '@/lib/format';
import type {
    PermissionSummary,
    RoleListItem,
    RoleListResponse,
} from '@/types/role';
import type { TeamListResponse } from '@/types/team';

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

/**
 * Cuentas activas de la agencia. No sale del listado de roles: sumar
 * `users_count` contaría dos veces a quien tenga dos roles. Viene de
 * `meta.stats` del equipo, que es donde vive esa cifra.
 */
const activeMembers = ref<number | null>(null);

const baseRoles = computed(() => roles.value.filter((role) => role.is_base));
const ownRoles = computed(() => roles.value.filter((role) => !role.is_base));

const totalPermissions = computed(() => catalog.value.length);
const moduleCount = computed(
    () => new Set(catalog.value.map((permission) => permission.module)).size,
);

const dialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit' | 'view'>('create');
const dialogRoleId = ref<number | null>(null);
const dialogSeed = ref<RoleSeed | null>(null);

const sheetOpen = ref(false);
const sheetRole = ref<RoleListItem | null>(null);

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

async function loadActiveMembers(): Promise<void> {
    if (!can('team.view')) {
        return;
    }

    try {
        const response = await fetch(
            indexUsers({ query: { per_page: 1 } }).url,
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as TeamListResponse;

        activeMembers.value = json.meta?.stats?.active ?? null;
    } catch {
        // La cifra es contexto, no el contenido de la pantalla: si no llega, la
        // tarjeta lo dice con un guion en vez de romper el módulo.
    }
}

function openCreate(): void {
    dialogMode.value = 'create';
    dialogRoleId.value = null;
    dialogSeed.value = null;
    dialogOpen.value = true;
}

function openPermissions(role: RoleListItem): void {
    sheetRole.value = role;
    sheetOpen.value = true;
}

function openEdit(role: RoleListItem): void {
    dialogMode.value = role.is_base || !canManage.value ? 'view' : 'edit';
    dialogRoleId.value = role.id;
    dialogSeed.value = null;
    dialogOpen.value = true;
}

function editFromSheet(role: RoleListItem): void {
    sheetOpen.value = false;
    openEdit(role);
}

/**
 * Duplicar un rol del sistema: el modal se abre en creación con sus permisos ya
 * marcados. Es la única forma de partir de `Operador` sin marcar 13 casillas.
 */
function duplicate(role: RoleListItem, permissions: string[]): void {
    sheetOpen.value = false;
    dialogMode.value = 'create';
    dialogRoleId.value = null;
    dialogSeed.value = {
        name: t(':role (copia)', { role: role.label }),
        description: role.description,
        permissions,
    };
    dialogOpen.value = true;
}

function onSaved(): void {
    toast.success(t('Rol guardado'));
    void load();
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

onMounted(() => {
    void load();
    void loadActiveMembers();
});
</script>

<template>
    <div>
        <Head :title="$t('Roles y permisos')" />

        <div class="px-4 py-6 md:px-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="$t('Roles y permisos')"
                    :description="
                        $t(
                            'Qué puede hacer cada rol dentro del panel. Los del sistema son iguales para todas las agencias; los propios los defines tú.',
                        )
                    "
                />
                <Button v-if="canManage" @click="openCreate">
                    <Plus class="size-4" />
                    {{ $t('Crear rol') }}
                </Button>
            </div>

            <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    :label="$t('Roles en uso')"
                    :value="formatNumber(roles.length)"
                    :detail="$t('del sistema y propios')"
                    :loading="loading"
                />
                <KpiCard
                    :label="$t('Permisos disponibles')"
                    :value="formatNumber(totalPermissions)"
                    :detail="
                        $tc('en :count módulo|en :count módulos', moduleCount)
                    "
                    :loading="loading"
                />
                <KpiCard
                    :label="$t('Miembros con acceso')"
                    :value="
                        activeMembers === null
                            ? '—'
                            : formatNumber(activeMembers)
                    "
                    :detail="$t('cuentas activas')"
                    :loading="loading"
                />
                <KpiCard
                    :label="$t('Roles propios')"
                    :value="formatNumber(ownRoles.length)"
                    :detail="$t('creados por la agencia')"
                    :loading="loading"
                />
            </div>

            <!-- Loading -->
            <div v-if="loading" class="mt-6 grid gap-3.5 lg:grid-cols-2">
                <Skeleton v-for="n in 4" :key="n" class="h-44 w-full" />
            </div>

            <!-- Error -->
            <div
                v-else-if="loadError"
                class="mt-6 rounded-2xl border border-border bg-card p-10 text-center"
            >
                <p class="text-sm text-destructive">
                    {{ $t('No se pudieron cargar los roles.') }}
                </p>
                <Button variant="outline" size="sm" class="mt-3" @click="load">
                    {{ $t('Reintentar') }}
                </Button>
            </div>

            <div v-else class="mt-7 space-y-7">
                <!-- Roles del sistema -->
                <section>
                    <div
                        class="flex flex-wrap items-end justify-between gap-2 pb-3.5"
                    >
                        <div class="space-y-1">
                            <h2
                                class="flex items-center gap-2 text-[19px] font-semibold"
                            >
                                <ShieldCheck
                                    class="size-4 text-muted-foreground"
                                />
                                {{ $t('Roles del sistema') }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    $t(
                                        'Vienen con MONTREE. Puedes ver sus permisos, no modificarlos.',
                                    )
                                }}
                            </p>
                        </div>
                        <MonoLabel>
                            {{
                                $tc(':count rol|:count roles', baseRoles.length)
                            }}
                        </MonoLabel>
                    </div>

                    <ul class="grid gap-3.5 lg:grid-cols-2">
                        <li
                            v-for="role in baseRoles"
                            :key="role.id"
                            class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-[18px]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1.5">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="text-base font-semibold">
                                            {{ role.label }}
                                        </h3>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[11.5px] font-semibold text-muted-foreground"
                                        >
                                            <Lock class="size-3" />
                                            {{ $t('Solo lectura') }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1"
                                    >
                                        <MonoLabel class="tabular-nums">
                                            {{
                                                $t(
                                                    ':count de :total permisos',
                                                    {
                                                        count: role.permissions_count,
                                                        total: totalPermissions,
                                                    },
                                                )
                                            }}
                                        </MonoLabel>
                                        <MonoLabel class="tabular-nums">
                                            {{
                                                $tc(
                                                    ':count miembro|:count miembros',
                                                    role.users_count,
                                                )
                                            }}
                                        </MonoLabel>
                                    </div>
                                </div>
                            </div>

                            <ScopeBar
                                class="max-w-[220px]"
                                :value="role.permissions_count"
                                :total="totalPermissions"
                                :label="t('Alcance del rol')"
                            />

                            <p
                                v-if="role.description"
                                class="text-[13.5px] text-muted-foreground"
                            >
                                {{ role.description }}
                            </p>

                            <div class="mt-auto flex flex-wrap gap-2 pt-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openPermissions(role)"
                                >
                                    <Eye class="size-4" />
                                    {{ $t('Ver permisos') }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Roles propios -->
                <section>
                    <div
                        class="flex flex-wrap items-end justify-between gap-2 pb-3.5"
                    >
                        <div class="space-y-1">
                            <h2 class="text-[19px] font-semibold">
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
                        <MonoLabel>
                            {{
                                $tc(':count rol|:count roles', ownRoles.length)
                            }}
                        </MonoLabel>
                    </div>

                    <div
                        v-if="ownRoles.length === 0"
                        class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-input bg-card p-10 text-center"
                    >
                        <p class="text-base font-medium">
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

                    <ul v-else class="grid gap-3.5 lg:grid-cols-2">
                        <li
                            v-for="role in ownRoles"
                            :key="role.id"
                            class="flex flex-col gap-3 rounded-2xl border border-border bg-card p-[18px]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1.5">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="text-base font-semibold">
                                            {{ role.label }}
                                        </h3>
                                        <span
                                            class="inline-flex items-center rounded-full bg-primary-soft px-2 py-0.5 text-[11.5px] font-semibold text-primary-readable"
                                        >
                                            {{ $t('Propio') }}
                                        </span>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-1"
                                    >
                                        <MonoLabel class="tabular-nums">
                                            {{
                                                $t(
                                                    ':count de :total permisos',
                                                    {
                                                        count: role.permissions_count,
                                                        total: totalPermissions,
                                                    },
                                                )
                                            }}
                                        </MonoLabel>
                                        <MonoLabel class="tabular-nums">
                                            {{
                                                $tc(
                                                    ':count miembro|:count miembros',
                                                    role.users_count,
                                                )
                                            }}
                                        </MonoLabel>
                                    </div>
                                </div>

                                <ActionMenu
                                    v-if="canManage"
                                    :label="
                                        t('Acciones de :role', {
                                            role: role.label,
                                        })
                                    "
                                >
                                    <DropdownMenuItem
                                        @select="openPermissions(role)"
                                    >
                                        <Eye class="size-4" />
                                        {{ $t('Ver permisos') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @select="openEdit(role)">
                                        <Pencil class="size-4" />
                                        {{ $t('Editar rol') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        :disabled="role.users_count > 0"
                                        class="text-brand-drop focus:text-brand-drop"
                                        @select="deleteTarget = role"
                                    >
                                        <Trash2 class="size-4" />
                                        {{
                                            role.users_count > 0
                                                ? $t('En uso: no se elimina')
                                                : $t('Eliminar rol')
                                        }}
                                    </DropdownMenuItem>
                                </ActionMenu>
                            </div>

                            <ScopeBar
                                class="max-w-[220px]"
                                :value="role.permissions_count"
                                :total="totalPermissions"
                                :label="t('Alcance del rol')"
                            />

                            <p
                                v-if="role.description"
                                class="text-[13.5px] text-muted-foreground"
                            >
                                {{ role.description }}
                            </p>
                            <p
                                v-else
                                class="text-[13.5px] text-muted-foreground/70 italic"
                            >
                                {{ $t('Sin descripción.') }}
                            </p>

                            <div class="mt-auto flex flex-wrap gap-2 pt-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openPermissions(role)"
                                >
                                    <Eye class="size-4" />
                                    {{ $t('Ver permisos') }}
                                </Button>
                                <Button
                                    v-if="canManage"
                                    variant="outline"
                                    size="sm"
                                    @click="openEdit(role)"
                                >
                                    <Pencil class="size-4" />
                                    {{ $t('Editar') }}
                                </Button>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <RolePermissionsSheet
            v-model:open="sheetOpen"
            :role="sheetRole"
            :catalog="catalog"
            :can-manage="canManage"
            @edit="editFromSheet"
            @duplicate="duplicate"
        />

        <RoleFormDialog
            v-model:open="dialogOpen"
            :role-id="dialogRoleId"
            :mode="dialogMode"
            :catalog="catalog"
            :seed="dialogSeed"
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
