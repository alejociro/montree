<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { show as showRole } from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import InitialsAvatar from '@/components/atoms/InitialsAvatar.vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ScopeBar from '@/components/atoms/ScopeBar.vue';
import PermissionSummaryList from '@/components/molecules/PermissionSummaryList.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/composables/useTranslations';
import type { PermissionSummary, RoleListItem } from '@/types/role';
import type { TeamMember } from '@/types/team';

/**
 * Panel lateral para cambiar el rol de un miembro.
 *
 * WHY: el modal anterior mostraba una lista de casillas sin decir qué implicaba
 * cada una, así que asignar «Operador» era un acto de fe. Acá, debajo del
 * selector, se leen los permisos resultantes agrupados por módulo — la misma
 * lectura que el módulo de Roles, en el momento en que se toma la decisión.
 *
 * Sigue siendo multi-rol: los permisos de un miembro son la UNIÓN de sus roles,
 * y el rediseño no recorta esa capacidad.
 */
const { t } = useTranslations();

type Props = {
    open: boolean;
    member: TeamMember | null;
    /** Roles asignables, con sus conteos. Los manda el módulo de roles. */
    roles: RoleListItem[];
    catalog: PermissionSummary[];
    saving?: boolean;
};

const props = withDefaults(defineProps<Props>(), { saving: false });

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'save', member: TeamMember, roles: string[]): void;
}>();

const draft = ref<string[]>([]);

/** Permisos por rol, cacheados por id: el panel se abre muchas veces. */
const permissionsByRole = ref<Record<number, string[]>>({});
const loadingPermissions = ref(false);

const selectedRoles = computed(() =>
    props.roles.filter((role) => draft.value.includes(role.name)),
);

/** La unión, que es exactamente lo que podrá hacer la persona. */
const granted = computed(() => {
    const union = new Set<string>();

    for (const role of selectedRoles.value) {
        for (const slug of permissionsByRole.value[role.id] ?? []) {
            union.add(slug);
        }
    }

    return [...union];
});

const changed = computed(() => {
    const before = [...(props.member?.roles ?? [])].sort().join(',');
    const after = [...draft.value].sort().join(',');

    return before !== after;
});

const canSave = computed(
    () => draft.value.length > 0 && changed.value && !props.saving,
);

function toggle(name: string): void {
    draft.value = draft.value.includes(name)
        ? draft.value.filter((role) => role !== name)
        : [...draft.value, name];
}

async function loadPermissionsFor(role: RoleListItem): Promise<void> {
    if (permissionsByRole.value[role.id] !== undefined) {
        return;
    }

    try {
        const response = await fetch(showRole.url(role.id), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as {
            data?: { permissions?: PermissionSummary[] };
            permissions?: PermissionSummary[];
        };

        const permissions = json.data?.permissions ?? json.permissions ?? [];

        permissionsByRole.value = {
            ...permissionsByRole.value,
            [role.id]: permissions.map((permission) => permission.slug),
        };
    } catch {
        // Sin el detalle la vista previa queda corta, pero el selector —que es
        // lo que guarda— sigue funcionando.
    }
}

async function loadSelectedPermissions(): Promise<void> {
    const pending = selectedRoles.value.filter(
        (role) => permissionsByRole.value[role.id] === undefined,
    );

    if (pending.length === 0) {
        return;
    }

    loadingPermissions.value = true;
    await Promise.all(pending.map((role) => loadPermissionsFor(role)));
    loadingPermissions.value = false;
}

watch(
    () => [props.open, props.member?.id] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        draft.value = [...(props.member?.roles ?? [])];
        void loadSelectedPermissions();
    },
    { immediate: true },
);

watch(draft, () => {
    void loadSelectedPermissions();
});

function save(): void {
    if (!props.member || !canSave.value) {
        return;
    }

    emit('save', props.member, [...draft.value]);
}
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent
            v-if="props.member"
            side="right"
            class="w-full gap-0 overflow-y-auto sm:max-w-[470px]"
        >
            <SheetHeader class="gap-1.5">
                <MonoLabel>{{ $t('Miembro') }}</MonoLabel>
                <div class="flex items-center gap-2.5">
                    <InitialsAvatar :name="props.member.name" />
                    <div class="min-w-0">
                        <SheetTitle class="text-lg">
                            {{ props.member.name }}
                        </SheetTitle>
                        <SheetDescription class="truncate">
                            {{ props.member.email }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="space-y-5 px-4 pb-6">
                <section class="space-y-2">
                    <div class="flex items-end justify-between gap-2">
                        <h3 class="text-sm font-semibold">
                            {{ $t('Roles asignados') }}
                        </h3>
                        <MonoLabel class="tabular-nums">
                            {{ $tc(':count rol|:count roles', draft.length) }}
                        </MonoLabel>
                    </div>

                    <fieldset class="space-y-1">
                        <legend class="sr-only">
                            {{ $t('Roles asignados') }}
                        </legend>
                        <button
                            v-for="role in props.roles"
                            :key="role.id"
                            type="button"
                            role="checkbox"
                            :aria-checked="draft.includes(role.name)"
                            class="flex w-full items-start gap-2.5 rounded-xl border p-3 text-left transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                draft.includes(role.name)
                                    ? 'border-primary/40 bg-primary-soft/60'
                                    : 'border-border hover:bg-muted/50'
                            "
                            @click="toggle(role.name)"
                        >
                            <Checkbox
                                class="pointer-events-none mt-0.5"
                                tabindex="-1"
                                aria-hidden="true"
                                :model-value="draft.includes(role.name)"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm font-medium text-foreground"
                                >
                                    {{ role.label }}
                                </span>
                                <span
                                    v-if="role.description"
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    {{ role.description }}
                                </span>
                                <span
                                    class="mt-1 block text-[11px] font-semibold tracking-[0.09em] text-muted-foreground uppercase tabular-nums"
                                >
                                    {{
                                        $t(':count de :total permisos', {
                                            count: role.permissions_count,
                                            total: props.catalog.length,
                                        })
                                    }}
                                </span>
                            </span>
                        </button>
                    </fieldset>

                    <p
                        v-if="draft.length === 0"
                        class="text-xs text-destructive"
                    >
                        {{ $t('Elige al menos un rol.') }}
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        {{
                            $t(
                                'Los permisos de un miembro son la suma de todos sus roles.',
                            )
                        }}
                    </p>
                </section>

                <section class="space-y-2">
                    <div class="flex items-end justify-between gap-2">
                        <h3 class="text-sm font-semibold">
                            {{ $t('Podrá hacer') }}
                        </h3>
                        <MonoLabel class="tabular-nums">
                            {{
                                $t(':count de :total permisos', {
                                    count: granted.length,
                                    total: props.catalog.length,
                                })
                            }}
                        </MonoLabel>
                    </div>

                    <ScopeBar
                        :value="granted.length"
                        :total="props.catalog.length"
                        :label="t('Alcance de los roles elegidos')"
                    />

                    <div v-if="loadingPermissions" class="space-y-3 pt-1">
                        <Skeleton v-for="n in 3" :key="n" class="h-24 w-full" />
                    </div>

                    <PermissionSummaryList
                        v-else
                        class="pt-1"
                        :catalog="props.catalog"
                        :granted="granted"
                    />
                </section>
            </div>

            <SheetFooter>
                <Button class="flex-1" :disabled="!canSave" @click="save">
                    <Check class="size-4" />
                    {{ props.saving ? $t('Guardando...') : $t('Guardar rol') }}
                </Button>
                <Button variant="outline" @click="emit('update:open', false)">
                    {{ $t('Cancelar') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
