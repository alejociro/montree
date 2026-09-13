<script setup lang="ts">
import { Check, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { groupPermissions } from '@/config/permissions';
import type { PermissionSummary } from '@/types/role';

/**
 * Qué puede y qué no puede hacer un rol, agrupado por módulo y de solo lectura.
 *
 * WHY: el rediseño pide leer los permisos —en el panel de un rol y en el de un
 * miembro— sin poder tocarlos. `PermissionPicker` no sirve para eso: es un
 * formulario, y desactivarlo entero deja una reja de casillas grises que se lee
 * como «apagado», no como «así es el rol».
 *
 * Los permisos que el rol NO tiene se muestran igual, en gris: el contraste es
 * la información. Un listado que solo enseña lo concedido obliga a recordar el
 * catálogo entero para saber qué falta.
 */
type Props = {
    /** Universo de permisos, el catálogo completo. */
    catalog: PermissionSummary[];
    /** Slugs que el rol tiene concedidos. */
    granted: string[];
};

const props = defineProps<Props>();

const grantedSet = computed(() => new Set(props.granted));

const modules = computed(() =>
    groupPermissions(props.catalog).map((module) => ({
        ...module,
        count: module.permissions.filter((permission) =>
            grantedSet.value.has(permission.slug),
        ).length,
    })),
);

function isGranted(slug: string): boolean {
    return grantedSet.value.has(slug);
}
</script>

<template>
    <div class="space-y-3">
        <section
            v-for="group in modules"
            :key="group.key"
            class="overflow-hidden rounded-xl border border-border"
        >
            <header
                class="flex items-center justify-between gap-2 border-b border-brand-line-2 bg-muted/40 px-3 py-2"
            >
                <span class="text-sm font-semibold">
                    {{ $t(group.label) }}
                </span>
                <MonoLabel class="tabular-nums">
                    {{ group.count }}/{{ group.permissions.length }}
                </MonoLabel>
            </header>

            <ul class="divide-y divide-brand-line-2">
                <li
                    v-for="permission in group.permissions"
                    :key="permission.slug"
                    class="flex items-center justify-between gap-3 px-3 py-2"
                >
                    <span
                        class="text-[13px]"
                        :class="
                            isGranted(permission.slug)
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ $t(permission.label) }}
                    </span>
                    <span
                        class="inline-flex size-5 shrink-0 items-center justify-center rounded-full"
                        :class="
                            isGranted(permission.slug)
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <component
                            :is="isGranted(permission.slug) ? Check : Minus"
                            class="size-3"
                            aria-hidden="true"
                        />
                        <span class="sr-only">
                            {{
                                isGranted(permission.slug)
                                    ? $t('Permitido')
                                    : $t('No permitido')
                            }}
                        </span>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
