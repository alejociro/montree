<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TenantPlan } from '@/types';

const props = defineProps<{
    currentPlan: TenantPlan;
    processing?: boolean;
}>();

const emit = defineEmits<{
    submit: [next: TenantPlan];
}>();

const selected = ref<TenantPlan>(props.currentPlan);

watch(
    () => props.currentPlan,
    (next) => {
        selected.value = next;
    },
);

const plans: { value: TenantPlan; label: string }[] = [
    { value: 'basic', label: 'Basic' },
    { value: 'professional', label: 'Professional' },
    { value: 'enterprise', label: 'Enterprise' },
];

function submit(): void {
    if (selected.value === props.currentPlan) {
        return;
    }

    emit('submit', selected.value);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Select v-model="selected" :disabled="processing">
            <SelectTrigger class="w-44">
                <SelectValue placeholder="Seleccionar plan" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem v-for="plan in plans" :key="plan.value" :value="plan.value">
                    {{ plan.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Button
            size="sm"
            :disabled="processing || selected === currentPlan"
            @click="submit"
        >
            {{ processing ? 'Procesando...' : 'Aplicar' }}
        </Button>
    </div>
</template>
