<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { TourFormStep, TourFormStepId } from '@/types/tour';

/**
 * Riel de progreso del formulario del tour: qué bloques ya están listos y a
 * cuál saltar. Es presentación pura — quién está «hecho» lo decide
 * `useTourCompletion`, contra las reglas del backend.
 */
type Props = {
    steps: TourFormStep[];
    activeId?: TourFormStepId | null;
};

const props = withDefaults(defineProps<Props>(), { activeId: null });

const emit = defineEmits<{
    (e: 'select', step: TourFormStep): void;
}>();
</script>

<template>
    <Card>
        <CardContent class="space-y-3">
            <MonoLabel>{{ $t('Progreso') }}</MonoLabel>

            <ol class="flex flex-col gap-0.5">
                <li v-for="(step, index) in props.steps" :key="step.id">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-brand-green-50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            props.activeId === step.id ? 'bg-secondary' : ''
                        "
                        :aria-current="
                            props.activeId === step.id ? 'step' : undefined
                        "
                        @click="emit('select', step)"
                    >
                        <span
                            :class="
                                cn(
                                    'mt-0.5 grid size-5.5 shrink-0 place-items-center rounded-full border-[1.5px] text-[11px] font-bold',
                                    step.done
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-input text-muted-foreground',
                                )
                            "
                        >
                            <Check v-if="step.done" class="size-3" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-medium">{{
                                step.label
                            }}</span>
                            <span
                                class="block truncate text-[11.5px] text-muted-foreground"
                                >{{ step.hint }}</span
                            >
                        </span>
                    </button>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
