<script setup lang="ts">
import { Check, Minus } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { groupPermissions } from '@/config/permissions';
import type { PermissionSummary } from '@/types/role';

type Props = {
    /** Slugs seleccionados. */
    modelValue: string[];
    /** Universo de permisos que se puede marcar, agrupado por modulo. */
    catalog: PermissionSummary[];
    /** Solo lectura: roles base (`is_base`) se ven pero no se editan. */
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), { disabled: false });

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();

/** Grupos con sus slugs ya extraidos: el template no calcula nada. */
const modules = computed(() =>
    groupPermissions(props.catalog).map((module) => ({
        ...module,
        slugs: module.permissions.map((permission) => permission.slug),
    })),
);

const selected = computed(() => new Set(props.modelValue));

const totalCount = computed(() => props.catalog.length);

function isChecked(slug: string): boolean {
    return selected.value.has(slug);
}

function selectedInModule(slugs: string[]): number {
    return slugs.filter((slug) => selected.value.has(slug)).length;
}

/** Estado del checkbox de modulo: todo, nada o mixto. */
function moduleState(slugs: string[]): boolean | 'indeterminate' {
    const count = selectedInModule(slugs);

    if (count === 0) {
        return false;
    }

    return count === slugs.length ? true : 'indeterminate';
}

/** El mismo estado en el vocabulario de ARIA, donde el mixto es `mixed`. */
function moduleAriaState(slugs: string[]): boolean | 'mixed' {
    const state = moduleState(slugs);

    return state === 'indeterminate' ? 'mixed' : state;
}

function emitFrom(next: Set<string>): void {
    // Se emite en el orden del catalogo, no en el de clic: asi dos selecciones
    // equivalentes producen el mismo payload.
    emit(
        'update:modelValue',
        props.catalog
            .map((permission) => permission.slug)
            .filter((slug) => next.has(slug)),
    );
}

function togglePermission(slug: string): void {
    if (props.disabled) {
        return;
    }

    const next = new Set(selected.value);

    if (next.has(slug)) {
        next.delete(slug);
    } else {
        next.add(slug);
    }

    emitFrom(next);
}

function toggleModule(slugs: string[]): void {
    if (props.disabled) {
        return;
    }

    const next = new Set(selected.value);
    const shouldSelectAll = selectedInModule(slugs) < slugs.length;

    for (const slug of slugs) {
        if (shouldSelectAll) {
            next.add(slug);
            continue;
        }

        next.delete(slug);
    }

    emitFrom(next);
}

function selectAll(): void {
    if (props.disabled) {
        return;
    }

    emitFrom(new Set(props.catalog.map((permission) => permission.slug)));
}

function clearAll(): void {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', []);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                <span class="font-medium text-foreground tabular-nums">
                    {{ modelValue.length }}
                </span>
                de {{ totalCount }} permisos seleccionados
            </p>
            <div v-if="!disabled" class="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="modelValue.length === totalCount"
                    @click="selectAll"
                >
                    Seleccionar todo
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="modelValue.length === 0"
                    @click="clearAll"
                >
                    Limpiar
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <fieldset
                v-for="group in modules"
                :key="group.key"
                class="rounded-xl border border-border p-3"
                :disabled="disabled"
            >
                <legend class="sr-only">{{ group.label }}</legend>

                <button
                    type="button"
                    role="checkbox"
                    :aria-checked="moduleAriaState(group.slugs)"
                    :disabled="disabled"
                    class="flex w-full items-center gap-2 rounded-md px-1 py-1 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed"
                    @click="toggleModule(group.slugs)"
                >
                    <Checkbox
                        class="pointer-events-none"
                        tabindex="-1"
                        aria-hidden="true"
                        :model-value="moduleState(group.slugs)"
                    >
                        <!-- Sin esto el estado mixto se ve igual que "todo
                             marcado": el indicador por defecto siempre es un
                             tilde. -->
                        <Minus
                            v-if="moduleState(group.slugs) === 'indeterminate'"
                            class="size-3.5"
                        />
                        <Check v-else class="size-3.5" />
                    </Checkbox>
                    <span class="text-sm font-semibold">{{ group.label }}</span>
                    <span
                        class="ml-auto text-xs text-muted-foreground tabular-nums"
                    >
                        {{ selectedInModule(group.slugs) }}/{{
                            group.slugs.length
                        }}
                    </span>
                </button>

                <ul class="mt-2 space-y-0.5">
                    <li
                        v-for="permission in group.permissions"
                        :key="permission.slug"
                    >
                        <button
                            type="button"
                            role="checkbox"
                            :aria-checked="isChecked(permission.slug)"
                            :disabled="disabled"
                            class="flex w-full items-start gap-2 rounded-md px-1 py-1.5 text-left hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:hover:bg-transparent"
                            @click="togglePermission(permission.slug)"
                        >
                            <Checkbox
                                class="pointer-events-none mt-0.5"
                                tabindex="-1"
                                aria-hidden="true"
                                :model-value="isChecked(permission.slug)"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm">
                                    {{ permission.label }}
                                </span>
                                <span
                                    class="block truncate font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ permission.slug }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </fieldset>
        </div>
    </div>
</template>
