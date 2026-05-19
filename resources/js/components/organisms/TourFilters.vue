<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { TourCategory, TourStatus } from '@/types/tour';

type FilterValue = {
    status: TourStatus | 'all';
    category_id: number | null;
    search: string;
};

type Props = {
    modelValue: FilterValue;
    categories: TourCategory[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: FilterValue): void;
}>();

const statusOptions = computed<
    { value: FilterValue['status']; label: string }[]
>(() => [
    { value: 'all', label: 'Todos' },
    { value: 'draft', label: 'Borradores' },
    { value: 'active', label: 'Activos' },
    { value: 'paused', label: 'Pausados' },
    { value: 'archived', label: 'Archivados' },
]);

function setStatus(value: FilterValue['status']): void {
    emit('update:modelValue', { ...props.modelValue, status: value });
}

function setCategory(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    const parsed = value === 'all' ? null : Number(value);
    emit('update:modelValue', { ...props.modelValue, category_id: parsed });
}

function setSearch(value: string | number): void {
    emit('update:modelValue', { ...props.modelValue, search: String(value) });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
    >
        <div class="flex flex-wrap gap-2">
            <Button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                size="sm"
                :variant="
                    modelValue.status === option.value ? 'default' : 'outline'
                "
                :class="
                    cn(
                        'h-8 px-3 text-xs',
                        modelValue.status === option.value && 'shadow-sm',
                    )
                "
                @click="setStatus(option.value)"
            >
                {{ option.label }}
            </Button>
        </div>

        <div class="flex flex-col gap-2 md:flex-row md:items-center">
            <Select
                :model-value="
                    modelValue.category_id === null
                        ? 'all'
                        : String(modelValue.category_id)
                "
                @update:model-value="setCategory"
            >
                <SelectTrigger class="w-full md:w-48">
                    <SelectValue placeholder="Categoría" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectItem value="all"
                            >Todas las categorías</SelectItem
                        >
                        <SelectItem
                            v-for="category in categories"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>

            <div class="relative w-full md:w-64">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    type="search"
                    placeholder="Buscar tours..."
                    class="pl-9"
                    :model-value="modelValue.search"
                    @update:model-value="setSearch"
                />
            </div>
        </div>
    </div>
</template>
