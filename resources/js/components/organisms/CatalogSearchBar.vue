<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

type Props = {
    modelValue: string;
};

defineProps<Props>();

defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <label class="relative block w-full">
        <span class="sr-only">Buscar tours</span>
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            type="search"
            :model-value="modelValue"
            placeholder="Buscar tours por nombre o descripción"
            class="pl-9"
            autocomplete="off"
            @update:model-value="
                (value) => $emit('update:modelValue', String(value ?? ''))
            "
        />
    </label>
</template>
