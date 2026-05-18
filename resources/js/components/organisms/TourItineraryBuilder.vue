<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { TourItineraryDraft } from '@/types/tour';

type Props = {
    modelValue: TourItineraryDraft[];
    errors?: Record<string, string | undefined>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourItineraryDraft[]): void;
}>();

function renumber(steps: TourItineraryDraft[]): TourItineraryDraft[] {
    return steps.map((step, index) => ({ ...step, step_number: index + 1 }));
}

function addStep(): void {
    const next: TourItineraryDraft = {
        step_number: props.modelValue.length + 1,
        title: '',
        description: '',
        duration_label: '',
    };
    emit('update:modelValue', [...props.modelValue, next]);
}

function removeStep(index: number): void {
    const updated = [...props.modelValue];
    updated.splice(index, 1);
    emit('update:modelValue', renumber(updated));
}

function moveStep(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.modelValue.length) {
        return;
    }

    const updated = [...props.modelValue];
    [updated[index], updated[target]] = [updated[target], updated[index]];
    emit('update:modelValue', renumber(updated));
}

function updateStep(
    index: number,
    key: keyof TourItineraryDraft,
    value: string,
): void {
    const updated = [...props.modelValue];
    updated[index] = { ...updated[index], [key]: value };
    emit('update:modelValue', updated);
}

function errorFor(index: number, field: string): string | undefined {
    return props.errors?.[`itinerary.${index}.${field}`];
}
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Itinerario"
                description="Paso a paso de la experiencia."
            />
            <Button type="button" size="sm" variant="outline" @click="addStep">
                <Plus class="size-4" />
                Agregar paso
            </Button>
        </div>

        <div
            v-if="modelValue.length === 0"
            class="rounded-md border border-dashed border-input p-6 text-center text-sm text-muted-foreground"
        >
            Aún no agregaste pasos. Empezá con la salida y la actividad
            principal.
        </div>

        <div
            v-for="(step, index) in modelValue"
            :key="index"
            class="space-y-3 rounded-lg border border-input bg-card p-4"
        >
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium"
                    >Paso {{ step.step_number }}</span
                >
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :disabled="index === 0"
                        @click="moveStep(index, -1)"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        :disabled="index === modelValue.length - 1"
                        @click="moveStep(index, 1)"
                    >
                        <ArrowDown class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        @click="removeStep(index)"
                    >
                        <Trash2 class="size-4 text-destructive" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-[1fr_140px]">
                <div class="grid gap-2">
                    <Label :for="`step-title-${index}`">Título</Label>
                    <Input
                        :id="`step-title-${index}`"
                        :model-value="step.title"
                        placeholder="Salida desde la plaza"
                        maxlength="120"
                        @update:model-value="
                            (v) => updateStep(index, 'title', String(v))
                        "
                    />
                    <InputError :message="errorFor(index, 'title')" />
                </div>

                <div class="grid gap-2">
                    <Label :for="`step-duration-${index}`">Duración</Label>
                    <Input
                        :id="`step-duration-${index}`"
                        :model-value="step.duration_label"
                        placeholder="30 min"
                        maxlength="30"
                        @update:model-value="
                            (v) =>
                                updateStep(index, 'duration_label', String(v))
                        "
                    />
                    <InputError :message="errorFor(index, 'duration_label')" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label :for="`step-description-${index}`">Descripción</Label>
                <Textarea
                    :id="`step-description-${index}`"
                    :model-value="step.description"
                    rows="2"
                    maxlength="2000"
                    placeholder="Detalles del paso"
                    @update:model-value="
                        (v) => updateStep(index, 'description', String(v))
                    "
                />
                <InputError :message="errorFor(index, 'description')" />
            </div>
        </div>
    </section>
</template>
