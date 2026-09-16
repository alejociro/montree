<script setup lang="ts">
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import type { RouteOption } from '@/types/logistics';
import type { TourRouteSelection } from '@/types/tour';

type Props = {
    modelValue: TourRouteSelection[];
    availableRoutes: RouteOption[];
    error?: string;
};

const props = withDefaults(defineProps<Props>(), { error: undefined });

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourRouteSelection[]): void;
}>();

const selectedIds = computed(
    () => new Set(props.modelValue.map((route) => route.id)),
);

const defaultId = computed<number | null>(
    () => props.modelValue.find((route) => route.is_default)?.id ?? null,
);

function toggle(routeId: number): void {
    if (!selectedIds.value.has(routeId)) {
        emit('update:modelValue', [
            ...props.modelValue,
            { id: routeId, is_default: props.modelValue.length === 0 },
        ]);

        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.filter((route) => route.id !== routeId),
    );
}

function markDefault(routeId: number): void {
    emit(
        'update:modelValue',
        props.modelValue.map((route) => ({
            ...route,
            is_default: route.id === routeId,
        })),
    );
}
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="availableRoutes.length === 0"
            class="rounded-md border border-dashed border-input p-4 text-sm text-muted-foreground"
        >
            {{
                $t(
                    'Todavía no tienes rutas en el catálogo de logística. Créalas allí para poder asociarlas.',
                )
            }}
        </p>

        <ul v-else class="divide-y divide-border rounded-md border border-input">
            <li
                v-for="route in availableRoutes"
                :key="route.id"
                class="flex flex-wrap items-center justify-between gap-3 p-3"
            >
                <button
                    type="button"
                    class="flex flex-1 items-center gap-3 text-left"
                    @click="toggle(route.id)"
                >
                    <Checkbox
                        :model-value="selectedIds.has(route.id)"
                        class="pointer-events-none"
                    />
                    <span>
                        <span class="text-sm font-medium">{{ route.name }}</span>
                        <span
                            v-if="route.kind"
                            class="ml-2 text-xs text-muted-foreground"
                        >
                            {{ route.kind }}
                        </span>
                    </span>
                </button>

                <label
                    v-if="selectedIds.has(route.id)"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <input
                        type="radio"
                        name="tour-default-route"
                        :checked="defaultId === route.id"
                        @change="markDefault(route.id)"
                    />
                    {{ $t('Predeterminada') }}
                </label>
            </li>
        </ul>

        <p class="text-xs text-muted-foreground">
            {{
                $t(
                    'La ruta predeterminada llega preseleccionada al crear una salida. Solo puedes elegir una.',
                )
            }}
        </p>

        <InputError :message="error" />
    </div>
</template>
