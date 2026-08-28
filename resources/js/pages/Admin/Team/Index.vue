<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Ban,
    ChevronLeft,
    ChevronRight,
    MailPlus,
    RotateCcw,
    Send,
    UserCog,
    Users,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { index as indexRoles } from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import {
    index as indexUsers,
    store as storeUser,
    updateRole,
    resend as resendInvitationRoute,
    suspend,
    reactivate,
} from '@/actions/App/Http/Controllers/Api/V1/Admin/TeamController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import KpiCard from '@/components/atoms/KpiCard.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ActionMenu from '@/components/molecules/ActionMenu.vue';
import type { CountTab } from '@/components/molecules/CountTabs.vue';
import FilterBar from '@/components/molecules/FilterBar.vue';
import MemberRoleSheet from '@/components/organisms/MemberRoleSheet.vue';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApi } from '@/composables/useApi';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import {
    FALLBACK_ROLE_OPTIONS,
    NON_ASSIGNABLE_ROLES,
    roleLabels,
} from '@/config/roles';
import { formatNumber, formatRelativeDate } from '@/lib/format';
import type { PaginationMeta } from '@/types/pagination';
import type {
    PermissionSummary,
    RoleListItem,
    RoleListResponse,
} from '@/types/role';
import type {
    RoleOption,
    TeamListResponse,
    TeamMember,
    TeamMemberPayload,
    TeamMemberStatus,
    TeamStats,
} from '@/types/team';

const { t } = useTranslations();

const api = useApi();
const { can } = usePermissions();
const currentUserId = usePage().props.auth?.user?.id;

const canInvite = computed(() => can('team.invite'));
const canUpdateRoles = computed(() => can('team.role.update'));
const canSuspend = computed(() => can('team.suspend'));

// 10 filas por página: el pedido es paginar a partir del undécimo miembro.
const PER_PAGE = 10;
const ALL = 'all';

const members = ref<TeamMember[]>([]);
const meta = ref<PaginationMeta | null>(null);
const stats = ref<TeamStats | null>(null);
const currentPage = ref(1);
const loading = ref(true);
const loadError = ref(false);

const searchInput = ref('');

const filters = reactive({
    search: '',
    status: ALL as TeamMemberStatus | typeof ALL,
    role: ALL as string,
});

const hasActiveFilters = computed(
    () =>
        filters.search !== '' || filters.status !== ALL || filters.role !== ALL,
);

const statusMeta: Record<TeamMemberStatus, { label: string; classes: string }> =
    {
        active: {
            label: t('Activo'),
            classes: 'bg-primary-soft text-primary-readable',
        },
        invited: {
            label: t('Invitado'),
            classes: 'bg-brand-warn-50 text-brand-warn',
        },
        suspended: {
            label: t('Suspendido'),
            classes: 'bg-muted text-muted-foreground',
        },
    };

/**
 * Bandejas del listado. El conteo por bandeja sale de `meta.stats`, que mira el
 * equipo completo; `invited` no tiene cifra propia ahí y por eso se deduce.
 */
const tabs = computed<CountTab[]>(() => {
    const totals = stats.value;

    return [
        { id: ALL, label: t('Todos'), count: totals?.total ?? null },
        { id: 'active', label: t('Activos'), count: totals?.active ?? null },
        {
            id: 'invited',
            label: t('Invitados'),
            count:
                totals === null
                    ? null
                    : totals.total - totals.active - totals.suspended,
        },
        {
            id: 'suspended',
            label: t('Suspendidos'),
            count: totals?.suspended ?? null,
        },
    ];
});

const resultLabel = computed(() => {
    if (meta.value === null || stats.value === null) {
        return null;
    }

    return t(':shown de :total', {
        shown: formatNumber(meta.value.total),
        total: formatNumber(stats.value.total),
    });
});

/**
 * Roles asignables. Se leen del modulo de roles (incluye los propios de la
 * agencia) y se cae al juego base si esa pantalla no esta disponible para este
 * usuario o el backend todavia no la expone.
 */
const roleOptions = ref<RoleOption[]>(FALLBACK_ROLE_OPTIONS);
/** El listado completo, con conteos: lo consume el panel de rol. */
const roleCatalog = ref<RoleListItem[]>([]);
const permissionCatalog = ref<PermissionSummary[]>([]);

function toStatus(value: string): TeamMemberStatus {
    return value === 'invited' || value === 'suspended' ? value : 'active';
}

/**
 * Normaliza el item del listado a `{ name, label }`. Desde Fase 3A `roles` es
 * una lista de objetos (`RoleSummaryResource`).
 */
function toRoleOptions(payload: TeamMemberPayload): RoleOption[] {
    const raw = payload.roles ?? [];

    return raw
        .map((role) => ({ name: role.name, label: role.label }))
        .filter((role) => role.name !== '');
}

function toMember(payload: TeamMemberPayload): TeamMember {
    const roles = toRoleOptions(payload);

    return {
        id: payload.id,
        name: payload.name,
        email: payload.email,
        roles: roles.map((role) => role.name),
        roleLabels: roles,
        status: toStatus(payload.status),
        joined_at: payload.joined_at ?? null,
        last_login_at: payload.last_login_at ?? null,
    };
}

type ListQuery = {
    page: number;
    per_page: number;
    search?: string;
    status?: TeamMemberStatus;
    role?: string;
};

function buildQuery(): ListQuery {
    const query: ListQuery = {
        page: currentPage.value,
        per_page: PER_PAGE,
    };

    if (filters.search !== '') {
        query.search = filters.search;
    }

    if (filters.status !== ALL) {
        query.status = filters.status;
    }

    if (filters.role !== ALL) {
        query.role = filters.role;
    }

    return query;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetch(indexUsers({ query: buildQuery() }).url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const json = (await response.json()) as TeamListResponse;

        members.value = (json.data ?? []).map(toMember);
        meta.value = json.meta ?? null;
        stats.value = json.meta?.stats ?? null;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

async function loadRoleOptions(): Promise<void> {
    if (!canUpdateRoles.value) {
        return;
    }

    try {
        const response = await fetch(indexRoles().url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as RoleListResponse;
        const list = (json.data ?? []).filter(
            (role) => !NON_ASSIGNABLE_ROLES.includes(role.name),
        );

        permissionCatalog.value = json.meta?.available_permissions ?? [];

        if (list.length === 0) {
            return;
        }

        roleCatalog.value = list;
        roleOptions.value = list.map((role) => ({
            name: role.name,
            label: role.label,
        }));
    } catch {
        // Sin el modulo de roles el selector sigue sirviendo con los roles base.
    }
}

function goToPage(page: number): void {
    if (page < 1 || (meta.value && page > meta.value.last_page)) {
        return;
    }

    currentPage.value = page;
    void load();
}

function resetFilters(): void {
    searchInput.value = '';
    filters.search = '';
    filters.status = ALL;
    filters.role = ALL;
}

const applySearch = useDebounceFn((value: string) => {
    filters.search = value.trim();
}, 350);

watch(searchInput, (value) => {
    void applySearch(value);
});

watch(
    () => ({ ...filters }),
    () => {
        currentPage.value = 1;
        void load();
    },
);

function handleTabChange(value: string): void {
    filters.status = value === ALL ? ALL : toStatus(value);
}

function handleRoleChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    filters.role = value;
}

/* ------------------------------------------------------------------ Invitar */

const inviteEmail = ref('');
const inviteName = ref('');
const inviteRole = ref<string>('guide');
const sending = ref(false);

/**
 * WHY: los errores del formulario de invitación salían como toast en la
 * esquina —desaparecían solos y no decían a qué campo pertenecían—. Ahora
 * viven debajo del campo, como en el resto de los formularios, y se limpian en
 * cuanto se corrige lo que fallaba.
 */
const inviteErrors = ref<Record<string, string | undefined>>({});

function clearInviteError(field: string): void {
    if (inviteErrors.value[field] !== undefined) {
        inviteErrors.value = { ...inviteErrors.value, [field]: undefined };
    }
}

function invite(): void {
    sending.value = true;
    inviteErrors.value = {};
    void api.post(
        storeUser().url,
        {
            email: inviteEmail.value,
            name: inviteName.value || null,
            role: inviteRole.value,
        },
        {
            onSuccess: () => {
                toast.success(t('Invitación enviada'));
                inviteEmail.value = '';
                inviteName.value = '';
                inviteErrors.value = {};
                void load();
            },
            onError: (errors) => {
                inviteErrors.value = errors;

                // `_global` no cuelga de ningún campo (plan agotado, permiso):
                // ese sí necesita el toast para verse.
                if (errors._global !== undefined) {
                    toast.error(errors._global);
                }
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

/* ------------------------------------------------------------- Roles (edición) */

const roleSheetMember = ref<TeamMember | null>(null);
const roleSheetOpen = ref(false);
const savingRoles = ref(false);

const confirmMember = ref<TeamMember | null>(null);
const confirmRoles = ref<string[]>([]);
const confirmMessage = ref('');

function openRoleSheet(member: TeamMember): void {
    roleSheetMember.value = member;
    roleSheetOpen.value = true;
}

/**
 * Cambio sensible: el que agrega o quita acceso de fondo. Es la misma regla que
 * antes de Fase 3A (promover a admin, sacarle el admin a alguien), traducida a
 * multi-rol. La tercera regla de entonces —degradar a `customer`— ya no existe:
 * `customer` no es un rol asignable desde el equipo (`TenantRoleCatalog::STAFF_ROLES`),
 * y quitarle a alguien todos sus roles no es posible (mínimo 1, front y backend).
 */
function sensitiveMessage(member: TeamMember, roles: string[]): string {
    const had = member.roles.includes('admin');
    const has = roles.includes('admin');

    if (!had && has) {
        return t(
            '¿Promover a :name a administrador? Tendrá acceso completo al panel.',
            { name: member.name },
        );
    }

    if (had && !has) {
        return t(
            '¿Quitarle el rol de administrador a :name? Perderá el control total del panel.',
            { name: member.name },
        );
    }

    return '';
}

function submitRoles(member: TeamMember, roles: string[]): void {
    const message = sensitiveMessage(member, roles);

    if (message !== '') {
        confirmMember.value = member;
        confirmRoles.value = [...roles];
        confirmMessage.value = message;
        roleSheetOpen.value = false;

        return;
    }

    applyRoles(member, roles);
}

function cancelConfirm(): void {
    const member = confirmMember.value;

    confirmMember.value = null;

    if (member) {
        // Vuelve al panel con la selección intacta, no la descarta.
        roleSheetMember.value = { ...member, roles: [...confirmRoles.value] };
        roleSheetOpen.value = true;
    }
}

function acceptConfirm(): void {
    const member = confirmMember.value;

    if (!member) {
        return;
    }

    applyRoles(member, confirmRoles.value);
    confirmMember.value = null;
}

function applyRoles(member: TeamMember, roles: string[]): void {
    savingRoles.value = true;
    void api.patch(
        updateRole.url(member.id),
        { roles },
        {
            onSuccess: () => {
                toast.success(t('Roles actualizados'));
                roleSheetOpen.value = false;
                roleSheetMember.value = null;
                void load();
            },
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ??
                        t('No se pudieron actualizar los roles.'),
                );
                void load();
            },
            onFinish: () => {
                savingRoles.value = false;
            },
        },
    );
}

/* ----------------------------------------------------- Estado de la membresía */

function doSuspend(memberId: number): void {
    void api.patch(
        suspend.url(memberId),
        {},
        {
            onSuccess: () => {
                toast.success(t('Miembro suspendido'));
                void load();
            },
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Error'),
        },
    );
}

function doReactivate(memberId: number): void {
    void api.patch(
        reactivate.url(memberId),
        {},
        {
            onSuccess: () => {
                toast.success(t('Miembro reactivado'));
                void load();
            },
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Error'),
        },
    );
}

const resendingFor = ref<number | null>(null);

function resendInvitation(member: TeamMember): void {
    resendingFor.value = member.id;
    void api.post(
        resendInvitationRoute.url(member.id),
        {},
        {
            onSuccess: () => {
                toast.success(
                    t('Invitación reenviada a :email', { email: member.email }),
                );
            },
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        t('No se pudo reenviar la invitación.'),
                ),
            onFinish: () => {
                resendingFor.value = null;
            },
        },
    );
}

function lastLoginLabel(member: TeamMember): string {
    return member.last_login_at === null
        ? t('Nunca')
        : formatRelativeDate(member.last_login_at);
}

/** Nadie se suspende a sí mismo: el backend lo rechaza con un 422. */
function isSelf(member: TeamMember): boolean {
    return member.id === currentUserId;
}

onMounted(() => {
    void load();
    void loadRoleOptions();
});
</script>

<template>
    <div>
        <Head :title="$t('Equipo')" />

        <div class="px-4 py-6 md:px-8">
            <Heading
                :title="$t('Equipo')"
                :description="
                    $t(
                        'Quién trabaja en tu agencia, con qué rol y desde cuándo.',
                    )
                "
            />

            <div class="mt-5 grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard
                    :label="$t('Miembros')"
                    :value="formatNumber(stats?.total ?? 0)"
                    :detail="$t('con cuenta creada')"
                    :loading="loading && stats === null"
                />
                <KpiCard
                    :label="$t('Activos')"
                    :value="formatNumber(stats?.active ?? 0)"
                    :detail="$t('pueden entrar al panel')"
                    :loading="loading && stats === null"
                />
                <KpiCard
                    :label="$t('Guías')"
                    :value="formatNumber(stats?.guides ?? 0)"
                    :detail="$t('disponibles para salidas')"
                    :loading="loading && stats === null"
                />
                <KpiCard
                    :label="$t('Suspendidos')"
                    :value="formatNumber(stats?.suspended ?? 0)"
                    :detail="$t('sin acceso al panel')"
                    :alert="(stats?.suspended ?? 0) > 0"
                    :loading="loading && stats === null"
                />
            </div>

            <!-- Invitar: una sola fila, no media pantalla -->
            <section
                v-if="canInvite"
                class="mt-5 rounded-2xl border border-border bg-card p-[18px]"
            >
                <div class="space-y-0.5">
                    <h2 class="text-base font-semibold">
                        {{ $t('Invitar miembro') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Recibe un correo con el enlace para crear su contraseña.',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="mt-3.5 grid content-start gap-3 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,0.9fr)_auto] md:items-start"
                >
                    <div class="grid content-start gap-1.5">
                        <Label for="invite-email">
                            {{ $t('Correo') }}
                            <span class="text-brand-drop">*</span>
                        </Label>
                        <Input
                            id="invite-email"
                            v-model="inviteEmail"
                            type="email"
                            autocomplete="email"
                            placeholder="persona@correo.com"
                            :aria-invalid="
                                inviteErrors.email !== undefined || undefined
                            "
                            @update:model-value="clearInviteError('email')"
                        />
                        <InputError :message="inviteErrors.email" />
                    </div>
                    <div class="grid content-start gap-1.5">
                        <Label for="invite-name">{{ $t('Nombre') }}</Label>
                        <Input
                            id="invite-name"
                            v-model="inviteName"
                            autocomplete="name"
                            :placeholder="$t('Nombre y apellido')"
                            :aria-invalid="
                                inviteErrors.name !== undefined || undefined
                            "
                            @update:model-value="clearInviteError('name')"
                        />
                        <InputError :message="inviteErrors.name" />
                    </div>
                    <div class="grid content-start gap-1.5">
                        <Label for="invite-role">{{ $t('Rol') }}</Label>
                        <!-- Un solo rol al invitar, igual que antes de Fase 3A:
                             `InviteMemberRequest` sigue pidiendo `role`. Lo que
                             cambia es de dónde salen las opciones — incluyen los
                             roles propios de la agencia. -->
                        <select
                            id="invite-role"
                            v-model="inviteRole"
                            class="flex h-10 w-full rounded-[10px] border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="option in roleOptions"
                                :key="option.name"
                                :value="option.name"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="inviteErrors.role" />
                    </div>
                    <div class="grid content-start gap-1.5">
                        <span class="hidden md:block md:h-[14px]" />
                        <Button
                            class="h-10"
                            :disabled="sending || !inviteEmail"
                            @click="invite"
                        >
                            <Send class="size-4" />
                            {{ sending ? $t('Enviando...') : $t('Invitar') }}
                        </Button>
                    </div>
                </div>
            </section>

            <FilterBar
                class="mt-5"
                :search="searchInput"
                :placeholder="$t('Buscar miembro por nombre, correo o rol')"
                :result-label="resultLabel"
                :tabs="tabs"
                :active-tab="filters.status"
                :tabs-label="$t('Estado del miembro')"
                search-id="team-search"
                @update:search="searchInput = $event"
                @update:active-tab="handleTabChange"
            >
                <template #selects>
                    <div class="flex items-center gap-2">
                        <MonoLabel as="span">{{ $t('Rol') }}</MonoLabel>
                        <Select
                            :model-value="filters.role"
                            @update:model-value="handleRoleChange"
                        >
                            <SelectTrigger
                                id="filter-role"
                                class="h-9 w-[190px] rounded-full"
                            >
                                <SelectValue :placeholder="$t('Todos')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="ALL">
                                        {{ $t('Todos los roles') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in roleOptions"
                                        :key="option.name"
                                        :value="option.name"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                </template>
            </FilterBar>

            <div class="mt-4 rounded-2xl border border-border bg-card">
                <!-- Loading -->
                <div v-if="loading" class="space-y-2 p-4">
                    <div
                        v-for="n in 5"
                        :key="n"
                        class="h-14 animate-pulse rounded-lg bg-muted"
                    />
                </div>

                <!-- Error -->
                <div v-else-if="loadError" class="p-10 text-center">
                    <p class="text-sm text-destructive">
                        {{ $t('No se pudo cargar el equipo.') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="load"
                    >
                        {{ $t('Reintentar') }}
                    </Button>
                </div>

                <!-- Empty -->
                <div
                    v-else-if="members.length === 0"
                    class="m-4 flex flex-col items-center gap-3 rounded-xl border border-dashed border-input p-12 text-center"
                >
                    <Users class="size-8 text-muted-foreground/40" />
                    <div class="space-y-1">
                        <p class="text-base font-medium">
                            {{
                                hasActiveFilters
                                    ? $t('Sin miembros para este filtro')
                                    : $t('Todavía no hay nadie en el equipo')
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? $t(
                                          'Prueba con otra bandeja o limpia el buscador.',
                                      )
                                    : $t(
                                          'Invita a tu primer miembro con el formulario de arriba.',
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

                <!-- Tabla -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-border text-left">
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Miembro')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Rol')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Estado')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3">{{
                                    $t('Último acceso')
                                }}</MonoLabel>
                                <MonoLabel as="th" class="px-4 py-3 text-right">
                                    {{ $t('Acciones') }}
                                </MonoLabel>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in members"
                                :key="member.id"
                                class="border-b border-brand-line-2 align-middle transition last:border-0 hover:bg-primary-soft/50"
                                :class="
                                    member.status === 'suspended'
                                        ? 'opacity-[.62]'
                                        : ''
                                "
                            >
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <InitialsAvatar :name="member.name" />
                                        <div class="min-w-0">
                                            <span
                                                class="block text-[14.5px] font-semibold text-foreground"
                                            >
                                                {{ member.name }}
                                            </span>
                                            <span
                                                class="block truncate text-xs text-muted-foreground"
                                            >
                                                {{ member.email }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div
                                        v-if="member.roleLabels.length > 0"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="role in member.roleLabels"
                                            :key="role.name"
                                            class="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-[11.5px] font-semibold text-muted-foreground"
                                        >
                                            {{ role.label }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">
                                        {{ $t('Sin rol') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                                        :class="
                                            statusMeta[member.status].classes
                                        "
                                    >
                                        {{ statusMeta[member.status].label }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3.5 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ lastLoginLabel(member) }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex justify-end">
                                        <ActionMenu
                                            :label="
                                                t('Acciones de :name', {
                                                    name: member.name,
                                                })
                                            "
                                        >
                                            <DropdownMenuItem
                                                v-if="canUpdateRoles"
                                                @select="openRoleSheet(member)"
                                            >
                                                <UserCog class="size-4" />
                                                {{ $t('Editar rol') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    canInvite &&
                                                    member.status === 'invited'
                                                "
                                                :disabled="
                                                    resendingFor === member.id
                                                "
                                                @select="
                                                    resendInvitation(member)
                                                "
                                            >
                                                <MailPlus class="size-4" />
                                                {{
                                                    resendingFor === member.id
                                                        ? $t('Reenviando...')
                                                        : $t(
                                                              'Reenviar invitación',
                                                          )
                                                }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    canSuspend &&
                                                    member.status ===
                                                        'suspended'
                                                "
                                                @select="
                                                    doReactivate(member.id)
                                                "
                                            >
                                                <RotateCcw class="size-4" />
                                                {{ $t('Reactivar acceso') }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-else-if="
                                                    canSuspend &&
                                                    !isSelf(member)
                                                "
                                                class="text-brand-drop focus:text-brand-drop"
                                                @select="doSuspend(member.id)"
                                            >
                                                <Ban class="size-4" />
                                                {{ $t('Suspender acceso') }}
                                            </DropdownMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div
                    v-if="!loading && !loadError && meta && meta.total > 0"
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 text-sm text-muted-foreground"
                >
                    <span>
                        {{
                            $t('Mostrando :from–:to de :total miembros', {
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
            </div>
        </div>

        <MemberRoleSheet
            v-model:open="roleSheetOpen"
            :member="roleSheetMember"
            :roles="roleCatalog"
            :catalog="permissionCatalog"
            :saving="savingRoles"
            @save="submitRoles"
        />

        <!-- Confirmación de cambios sensibles -->
        <Dialog
            :open="confirmMember !== null"
            @update:open="(value: boolean) => !value && cancelConfirm()"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        $t('Confirmar cambio de roles')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ confirmMessage }}
                    </DialogDescription>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('Quedará con: :roles', {
                            roles: roleLabels(confirmRoles, roleOptions),
                        })
                    }}
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="cancelConfirm">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button :disabled="savingRoles" @click="acceptConfirm">
                        {{ $t('Confirmar') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
