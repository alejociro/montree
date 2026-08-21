<script setup lang="ts">
import { Check, Circle } from 'lucide-vue-next';
import { computed } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { TourPublishRequirement } from '@/types/tour';

/**
 * «Para publicar»: qué le falta al tour para dejar de ser borrador.
 *
 * WHY (D7): el checklist avisa, no inventa reglas. `blocking` marca lo que el
 * backend rechaza de verdad —los campos obligatorios del Form Request, y la
 * imagen y el guía por defecto de `ChangeTourStatusAction`—; el resto se lista
 * como recomendado, con la misma tipografía pero sin prometer que bloquea.
 */
type Props = {
    requirements: TourPublishRequirement[];
};

const props = defineProps<Props>();

const blocking = computed<TourPublishRequirement[]>(() =>
    props.requirements.filter((requirement) => requirement.blocking),
);

const recommended = computed<TourPublishRequirement[]>(() =>
    props.requirements.filter((requirement) => !requirement.blocking),
);

const pending = computed<number>(
    () => blocking.value.filter((requirement) => !requirement.done).length,
);
</script>

<template>
    <Card>
        <CardContent class="space-y-3">
            <MonoLabel>{{ $t('Para publicar') }}</MonoLabel>

            <ul class="space-y-1.5">
                <li
                    v-for="requirement in blocking"
                    :key="requirement.id"
                    class="flex items-start gap-2 text-[13px]"
                >
                    <Check
                        v-if="requirement.done"
                        class="mt-0.5 size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <Circle
                        v-else
                        class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span :class="requirement.done ? '' : 'text-foreground'">
                        {{ requirement.label }}
                        <span class="sr-only">{{
                            requirement.done
                                ? $t('Listo')
                                : $t('Pendiente y obligatorio')
                        }}</span>
                        <span
                            v-if="requirement.hint"
                            class="block text-[11.5px] text-muted-foreground"
                            >{{ requirement.hint }}</span
                        >
                    </span>
                </li>
            </ul>

            <div
                v-if="recommended.length > 0"
                class="space-y-1.5 border-t border-brand-line-2 pt-3"
            >
                <MonoLabel>{{ $t('Recomendado') }}</MonoLabel>
                <ul class="space-y-1.5">
                    <li
                        v-for="requirement in recommended"
                        :key="requirement.id"
                        class="flex items-start gap-2 text-[13px] text-muted-foreground"
                    >
                        <Check
                            v-if="requirement.done"
                            class="mt-0.5 size-4 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <Circle
                            v-else
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            {{ requirement.label }}
                            <span class="sr-only">{{
                                requirement.done
                                    ? $t('Listo')
                                    : $t('Pendiente, no bloquea')
                            }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <p
                v-if="pending > 0"
                class="rounded-lg bg-brand-warn-50 px-3 py-2 text-[11.5px] text-brand-warn"
            >
                {{
                    $t(
                        'Mientras falte algo obligatorio el tour queda en borrador y no aparece en el catálogo.',
                    )
                }}
            </p>
            <p v-else class="text-[11.5px] text-muted-foreground">
                {{
                    $t(
                        'Cumple las condiciones para publicarse. Se activa desde la edición del tour.',
                    )
                }}
            </p>
        </CardContent>
    </Card>
</template>
