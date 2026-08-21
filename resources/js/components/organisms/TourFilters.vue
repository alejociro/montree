<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/composables/useTranslations';
import { categoryLabel } from '@/lib/categories';
import { cn } from '@/lib/utils';
import { TOUR_STATUSES } from '@/types/tour';
import type {
    TourCategory,
    TourIndexFilters,
    TourSortValue,
} from '@/types/tour';

const { t } = useTranslations();

type Props = {
    modelValue: TourIndexFilters;
    categories: TourCategory[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: TourIndexFilters): void;
}>();

const statusLabels: Record<TourIndexFilters['status'], string> = {
    all: t('Todos'),
    draft: t('Borradores'),
    active: t('Activos'),
    paused: t('Pausados'),
    archived: t('Archivados'),
};

const statusOptions = computed<
    { value: TourIndexFilters['status']; label: string }[]
>(() =>
    (['all', ...TOUR_STATUSES] as TourIndexFilters['status'][]).map(
        (value) => ({ value, label: statusLabels[value] }),
    ),
);

/**
 * WHY: solo los órdenes que `TourController@index` sabe aplicar
 * (`SORTABLE_COLUMNS`). «Próxima salida», «ocupación» e «ingresos» del handoff
 * necesitan datos agregados que la API todavía no expone.
 */
const sortOptions = computed<{ value: TourSortValue; label: string }[]>(() => [
    { value: 'recent', label: t('Ordenar: más recientes') },
    { value: 'name', label: t('Ordenar: alfabético') },
    { value: 'price_desc', label: t('Ordenar: precio mayor') },
    { value: 'price_asc', label: t('Ordenar: precio menor') },
]);

function setStatus(value: TourIndexFilters['status']): void {
    emit('update:modelValue', { ...props.modelValue, status: value });
}

function setCategory(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    const parsed = value === 'all' ? null : Number(value);
    emit('update:modelValue', { ...props.modelValue, category_id: parsed });
}

function setSort(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    const option = sortOptions.value.find((item) => item.value === value);

    if (option === undefined) {
        return;
    }

    emit('update:modelValue', { ...props.modelValue, sort: option.value });
}

function setSearch(value: string | number): void {
    emit('update:modelValue', { ...props.modelValue, search: String(value) });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2.5">
        <div class="flex flex-wrap gap-1" role="tablist">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                role="tab"
                :aria-selected="modelValue.status === option.value"
                :class="
                    cn(
                        'rounded-full px-3.5 py-1.5 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none',
                        modelValue.status === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-brand-green-50 hover:text-foreground',
                    )
                "
                @click="setStatus(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <div class="relative min-w-[220px] flex-1 md:max-w-[340px]">
            <label class="sr-only" for="tour-search">
                {{ $t('Buscar tours...') }}
            </label>
            <Search
                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                id="tour-search"
                type="search"
                :placeholder="$t('Buscar tours...')"
                class="pl-9"
                :model-value="modelValue.search"
                @update:model-value="setSearch"
            />
        </div>

        <Select
            :model-value="
                modelValue.category_id === null
                    ? 'all'
                    : String(modelValue.category_id)
            "
            @update:model-value="setCategory"
        >
            <SelectTrigger
                class="w-full sm:w-auto sm:min-w-[180px]"
                :aria-label="$t('Categoría')"
            >
                <SelectValue :placeholder="$t('Categoría')" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem value="all">
                        {{ $t('Todas las categorías') }}
                    </SelectItem>
                    <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ categoryLabel(category.name) }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>

        <Select :model-value="modelValue.sort" @update:model-value="setSort">
            <SelectTrigger
                class="w-full sm:w-auto sm:min-w-[190px]"
                :aria-label="$t('Ordenar')"
            >
                <SelectValue :placeholder="$t('Ordenar')" />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    <SelectItem
                        v-for="option in sortOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectGroup>
            </SelectContent>
        </Select>
    </div>
</template>
