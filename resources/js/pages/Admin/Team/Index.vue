<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    MailPlus,
    Search,
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
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatRelativeDate } from '@/lib/format';
import type { PaginationMeta } from '@/types/pagination';
import type { RoleListResponse } from '@/types/role';
import type {
    RoleOption,
    TeamListResponse,
    TeamMember,
    TeamMemberPayload,
    TeamMemberStatus,
} from '@/types/team';

const { t } = useTranslations();

const api = useApi();
const { can } = usePermissions();
const currentUserId = usePage().props.auth?.user?.id;

const canInvite = computed(() => can('team.invite'));
const canUpdateRoles = computed(() => can('team.role.update'));
const canSuspend = computed(() => can('team.suspend'));

const PER_PAGE = 15;
const ALL = 'all';

const members = ref<TeamMember[]>([]);
const meta = ref<PaginationMeta | null>(null);
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

const statusOptions: { value: TeamMemberStatus; label: string }[] = [
    { value: 'active', label: t('Activo') },
    { value: 'invited', label: t('Invitado') },
    { value: 'suspended', label: t('Suspendido') },
];

const statusMeta: Record<
    TeamMemberStatus,
    { label: string; variant: 'default' | 'secondary' | 'destructive' }
> = {
    active: { label: t('Activo'), variant: 'secondary' },
    invited: { label: t('Invitado'), variant: 'default' },
    suspended: { label: t('Suspendido'), variant: 'destructive' },
};

/**
 * Roles asignables. Se leen del modulo de roles (incluye los propios de la
 * agencia) y se cae al juego base si esa pantalla no esta disponible para este
 * usuario o el backend todavia no la expone.
 */
const roleOptions = ref<RoleOption[]>(FALLBACK_ROLE_OPTIONS);

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
        const list = json.data ?? [];

        if (list.length === 0) {
            return;
        }

        roleOptions.value = list
            .filter((role) => !NON_ASSIGNABLE_ROLES.includes(role.name))
            .map((role) => ({ name: role.name, label: role.label }));
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

function handleStatusChange(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

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

function invite(): void {
    sending.value = true;
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
                void load();
            },
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Error'),
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

/* ------------------------------------------------------------- Roles (edición) */

const roleDialogMember = ref<TeamMember | null>(null);
const roleDraft = ref<string[]>([]);
const savingRoles = ref(false);

const confirmMember = ref<TeamMember | null>(null);
const confirmRoles = ref<string[]>([]);
const confirmMessage = ref('');

function openRoleDialog(member: TeamMember): void {
    roleDialogMember.value = member;
    roleDraft.value = [...member.roles];
}

function toggleDraftRole(name: string): void {
    roleDraft.value = roleDraft.value.includes(name)
        ? roleDraft.value.filter((role) => role !== name)
        : [...roleDraft.value, name];
}

const draftChanged = computed(() => {
    const member = roleDialogMember.value;

    if (!member) {
        return false;
    }

    const before = [...member.roles].sort().join(',');
    const after = [...roleDraft.value].sort().join(',');

    return before !== after;
});

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
        return `¿Promover a ${member.name} a administrador? Tendrá acceso completo al panel.`;
    }

    if (had && !has) {
        return `¿Quitarle el rol de administrador a ${member.name}? Perderá el control total del panel.`;
    }

    return '';
}

function submitRoles(): void {
    const member = roleDialogMember.value;

    if (!member || roleDraft.value.length === 0 || !draftChanged.value) {
        return;
    }

    const message = sensitiveMessage(member, roleDraft.value);

    if (message !== '') {
        confirmMember.value = member;
        confirmRoles.value = [...roleDraft.value];
        confirmMessage.value = message;
        roleDialogMember.value = null;

        return;
    }

    applyRoles(member, roleDraft.value);
}

function cancelConfirm(): void {
    const member = confirmMember.value;

    confirmMember.value = null;

    if (member) {
        // Vuelve al selector con la selección intacta, no la descarta.
        roleDialogMember.value = member;
        roleDraft.value = [...confirmRoles.value];
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
                roleDialogMember.value = null;
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
                toast.success(`Invitación reenviada a ${member.email}`);
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
        ? 'Nunca'
        : formatRelativeDate(member.last_login_at);
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
                        'Quién trabaja en tu agencia, con qué roles y desde cuándo.',
                    )
                "
            />

            <section
                v-if="canInvite"
                class="mt-6 space-y-4 rounded-2xl border border-border bg-card p-4 md:p-6"
            >
                <h2 class="text-lg font-semibold">
                    {{ $t('Invitar miembro') }}
                </h2>
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="invite-email">{{ $t('Email') }}</Label>
                        <Input
                            id="invite-email"
                            v-model="inviteEmail"
                            type="email"
                            autocomplete="email"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="invite-name">{{ $t('Nombre') }}</Label>
                        <Input
                            id="invite-name"
                            v-model="inviteName"
                            autocomplete="name"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="invite-role">{{ $t('Rol') }}</Label>
                        <!-- Un solo rol al invitar, igual que antes de Fase 3A:
                             `InviteMemberRequest` sigue pidiendo `role`. Lo que
                             cambia es de dónde salen las opciones — incluyen los
                             roles propios de la agencia. -->
                        <select
                            id="invite-role"
                            v-model="inviteRole"
                            class="flex h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="option in roleOptions"
                                :key="option.name"
                                :value="option.name"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <Button :disabled="sending || !inviteEmail" @click="invite">
                    {{ sending ? 'Enviando...' : 'Invitar' }}
                </Button>
            </section>

            <div class="mt-6 rounded-2xl border border-border bg-card">
                <!-- Filtros -->
                <div
                    class="flex flex-wrap items-end gap-3 border-b border-border p-4"
                >
                    <div class="space-y-1.5">
                        <Label for="filter-search">{{ $t('Buscar') }}</Label>
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                id="filter-search"
                                v-model="searchInput"
                                type="search"
                                :placeholder="$t('Nombre o email')"
                                class="w-[240px] pl-9"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-status">{{ $t('Estado') }}</Label>
                        <Select
                            :model-value="filters.status"
                            @update:model-value="handleStatusChange"
                        >
                            <SelectTrigger id="filter-status" class="w-[170px]">
                                <SelectValue :placeholder="$t('Todos')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="ALL">
                                        {{ $t('Todos los estados') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in statusOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="filter-role">{{ $t('Rol') }}</Label>
                        <Select
                            :model-value="filters.role"
                            @update:model-value="handleRoleChange"
                        >
                            <SelectTrigger id="filter-role" class="w-[190px]">
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

                    <Button
                        v-if="hasActiveFilters"
                        variant="ghost"
                        size="sm"
                        class="ml-auto"
                        @click="resetFilters"
                    >
                        {{ $t('Limpiar filtros') }}
                    </Button>
                </div>

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
                    class="flex flex-col items-center gap-3 p-12 text-center"
                >
                    <Users class="size-8 text-muted-foreground/40" />
                    <div class="space-y-1">
                        <p class="font-medium">
                            {{
                                hasActiveFilters
                                    ? 'Sin miembros para estos filtros'
                                    : 'Todavía no hay nadie en el equipo'
                            }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                hasActiveFilters
                                    ? 'Prueba ajustar o limpiar los filtros.'
                                    : 'Invita a tu primer miembro con el formulario de arriba.'
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
                            <tr
                                class="border-b border-border text-left text-xs font-medium text-muted-foreground"
                            >
                                <th class="px-4 py-3">{{ $t('Miembro') }}</th>
                                <th class="px-4 py-3">{{ $t('Roles') }}</th>
                                <th class="px-4 py-3">{{ $t('Estado') }}</th>
                                <th class="px-4 py-3">
                                    {{ $t('Último acceso') }}
                                </th>
                                <th class="px-4 py-3 text-right">
                                    {{ $t('Acciones') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in members"
                                :key="member.id"
                                class="border-b border-border align-middle last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <span class="block font-medium">
                                        {{ member.name }}
                                    </span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ member.email }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="member.roleLabels.length > 0"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <Badge
                                            v-for="role in member.roleLabels"
                                            :key="role.name"
                                            variant="secondary"
                                            class="font-normal"
                                        >
                                            {{ role.label }}
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground">
                                        {{ $t('Sin rol') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            statusMeta[member.status].variant
                                        "
                                    >
                                        {{ statusMeta[member.status].label }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ lastLoginLabel(member) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex flex-wrap items-center justify-end gap-2"
                                    >
                                        <Button
                                            v-if="
                                                canInvite &&
                                                member.status === 'invited'
                                            "
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                resendingFor === member.id
                                            "
                                            @click="resendInvitation(member)"
                                        >
                                            <MailPlus class="size-4" />
                                            {{
                                                resendingFor === member.id
                                                    ? 'Reenviando...'
                                                    : 'Reenviar invitación'
                                            }}
                                        </Button>
                                        <Button
                                            v-if="canUpdateRoles"
                                            variant="outline"
                                            size="sm"
                                            @click="openRoleDialog(member)"
                                        >
                                            <UserCog class="size-4" />
                                            {{ $t('Roles') }}
                                        </Button>
                                        <Button
                                            v-if="
                                                canSuspend &&
                                                member.status !== 'suspended' &&
                                                member.id !== currentUserId
                                            "
                                            variant="outline"
                                            size="sm"
                                            @click="doSuspend(member.id)"
                                        >
                                            {{ $t('Suspender') }}
                                        </Button>
                                        <Button
                                            v-else-if="
                                                canSuspend &&
                                                member.status === 'suspended'
                                            "
                                            variant="outline"
                                            size="sm"
                                            @click="doReactivate(member.id)"
                                        >
                                            {{ $t('Reactivar') }}
                                        </Button>
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

        <!-- Selector de roles -->
        <Dialog
            :open="roleDialogMember !== null"
            @update:open="
                (value: boolean) => !value && (roleDialogMember = null)
            "
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {{
                            $t('Roles de :name', {
                                name: roleDialogMember?.name ?? '',
                            })
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Un miembro puede tener varios roles: sus permisos son la suma de todos.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <fieldset class="space-y-0.5">
                    <legend class="sr-only">{{ $t('Roles asignados') }}</legend>
                    <button
                        v-for="option in roleOptions"
                        :key="option.name"
                        type="button"
                        role="checkbox"
                        :aria-checked="roleDraft.includes(option.name)"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="toggleDraftRole(option.name)"
                    >
                        <Checkbox
                            class="pointer-events-none"
                            tabindex="-1"
                            aria-hidden="true"
                            :model-value="roleDraft.includes(option.name)"
                        />
                        <span class="text-sm">{{ option.label }}</span>
                    </button>
                </fieldset>

                <p
                    v-if="roleDraft.length === 0"
                    class="text-xs text-destructive"
                >
                    {{ $t('Elige al menos un rol.') }}
                </p>

                <DialogFooter>
                    <Button variant="outline" @click="roleDialogMember = null">
                        {{ $t('Cancelar') }}
                    </Button>
                    <Button
                        :disabled="
                            savingRoles ||
                            roleDraft.length === 0 ||
                            !draftChanged
                        "
                        @click="submitRoles"
                    >
                        {{ savingRoles ? 'Guardando...' : 'Guardar' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
