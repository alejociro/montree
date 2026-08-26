<script setup lang="ts">
import { Copy, Lock, Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { show as showRole } from '@/actions/App/Http/Controllers/Api/V1/Admin/RoleController';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import ScopeBar from '@/components/atoms/ScopeBar.vue';
import PermissionSummaryList from '@/components/molecules/PermissionSummaryList.vue';
import { Button } from '@/components/ui/button';
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
import type { PermissionSummary, RoleDetail, RoleListItem } from '@/types/role';

/**
 * Panel lateral con los permisos de un rol.
 *
 * WHY: antes «Ver permisos» abría el mismo modal del formulario, así que un rol
 * del sistema se leía en una reja de casillas deshabilitadas. Consultar y
 * editar son dos tareas distintas: la consulta vive acá —panel de 470 px, solo
 * lectura, con el contador por módulo— y la edición sigue en su modal.
 */
const { t } = useTranslations();

type Props = {
    open: boolean;
    /** Fila del listado: da nombre, descripción y conteos sin esperar al detalle. */
    role: RoleListItem | null;
    catalog: PermissionSummary[];
    /** Sin `team.role.update` el panel no ofrece editar ni duplicar. */
    canManage?: boolean;
};

const props = withDefaults(defineProps<Props>(), { canManage: false });

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'edit', role: RoleListItem): void;
    (e: 'duplicate', role: RoleListItem, permissions: string[]): void;
}>();

const detail = ref<RoleDetail | null>(null);
const loading = ref(false);
const loadError = ref(false);

const granted = computed(() =>
    (detail.value?.permissions ?? []).map((permission) => permission.slug),
);

const total = computed(() => props.catalog.length);

const description = computed(
    () => props.role?.description ?? detail.value?.description ?? '',
);

async function loadDetail(roleId: number): Promise<void> {
    loading.value = true;
    loadError.value = false;
    detail.value = null;

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

        detail.value = 'data' in json ? json.data : json;
    } catch {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.role?.id] as const,
    ([open, roleId]) => {
        if (!open || roleId === undefined) {
            return;
        }

        void loadDetail(roleId);
    },
    { immediate: true },
);
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent
            v-if="props.role"
            side="right"
            class="w-full gap-0 overflow-y-auto sm:max-w-[470px]"
        >
            <SheetHeader class="gap-1.5">
                <MonoLabel>
                    {{
                        props.role.is_base
                            ? $t('Rol del sistema')
                            : $t('Rol propio')
                    }}
                </MonoLabel>
                <SheetTitle class="text-xl">{{ props.role.label }}</SheetTitle>
                <SheetDescription v-if="description !== ''">
                    {{ description }}
                </SheetDescription>

                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-[11.5px] font-semibold text-primary-readable tabular-nums"
                    >
                        {{
                            $t(':count de :total permisos', {
                                count: props.role.permissions_count,
                                total,
                            })
                        }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[11.5px] font-semibold text-muted-foreground tabular-nums"
                    >
                        {{
                            $tc(
                                ':count miembro|:count miembros',
                                props.role.users_count,
                            )
                        }}
                    </span>
                    <span
                        v-if="props.role.is_base"
                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-[11.5px] font-semibold text-muted-foreground"
                    >
                        <Lock class="size-3" />
                        {{ $t('Solo lectura') }}
                    </span>
                </div>

                <ScopeBar
                    class="mt-2"
                    :value="props.role.permissions_count"
                    :total="total"
                    :label="t('Alcance del rol')"
                />
            </SheetHeader>

            <div class="space-y-4 px-4 pb-6">
                <p
                    v-if="props.role.is_base"
                    class="rounded-xl border border-border bg-muted/40 p-3 text-[13px] text-muted-foreground"
                >
                    {{
                        $t(
                            'Este rol lo administra MONTREE y es igual para todas las agencias. Si necesitas otra combinación, duplícalo como rol propio.',
                        )
                    }}
                </p>

                <div v-if="loading" class="space-y-3">
                    <Skeleton v-for="n in 4" :key="n" class="h-24 w-full" />
                </div>

                <div
                    v-else-if="loadError"
                    class="rounded-xl border border-border p-8 text-center"
                >
                    <p class="text-sm text-destructive">
                        {{ $t('No se pudieron cargar los permisos.') }}
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="props.role && loadDetail(props.role.id)"
                    >
                        {{ $t('Reintentar') }}
                    </Button>
                </div>

                <PermissionSummaryList
                    v-else
                    :catalog="props.catalog"
                    :granted="granted"
                />
            </div>

            <SheetFooter v-if="props.canManage && !loadError">
                <Button
                    v-if="props.role.is_base"
                    variant="outline"
                    class="flex-1"
                    :disabled="loading"
                    @click="emit('duplicate', props.role, granted)"
                >
                    <Copy class="size-4" />
                    {{ $t('Duplicar como rol propio') }}
                </Button>
                <Button v-else class="flex-1" @click="emit('edit', props.role)">
                    <Pencil class="size-4" />
                    {{ $t('Editar rol') }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
