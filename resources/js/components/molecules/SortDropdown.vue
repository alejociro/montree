<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CATALOG_SORT_LABELS, CATALOG_SORTS } from '@/types/catalog';
import type { CatalogSort } from '@/types/catalog';

type Props = {
    modelValue: CatalogSort;
};

defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: CatalogSort): void;
}>();

function handleUpdate(value: unknown): void {
    if (typeof value !== 'string') {
        return;
    }

    if ((CATALOG_SORTS as string[]).includes(value)) {
        emit('update:modelValue', value as CatalogSort);
    }
}
</script>

<template>
    <Select :model-value="modelValue" @update:model-value="handleUpdate">
        <SelectTrigger class="w-full sm:w-56" aria-label="Ordenar por">
            <SelectValue placeholder="Ordenar por" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem v-for="sort in CATALOG_SORTS" :key="sort" :value="sort">
                {{ CATALOG_SORT_LABELS[sort] }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
