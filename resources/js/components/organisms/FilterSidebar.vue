<script setup lang="ts">
import CategoryChip from '@/components/molecules/CategoryChip.vue';
import PriceRangeFilter from '@/components/molecules/PriceRangeFilter.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { CatalogCategory } from '@/types/catalog';
import { TOUR_DIFFICULTIES } from '@/types/tour';
import type { TourDifficulty } from '@/types/tour';

type Props = {
    categories: CatalogCategory[];
    selectedCategory: string | null;
    selectedDifficulty: TourDifficulty | null;
    priceMin: number | null;
    priceMax: number | null;
    hasActiveFilters: boolean;
};

defineProps<Props>();

const emit = defineEmits<{
    (event: 'update:category', value: string | null): void;
    (event: 'update:difficulty', value: TourDifficulty | null): void;
    (event: 'update:priceMin', value: number | null): void;
    (event: 'update:priceMax', value: number | null): void;
    (event: 'reset'): void;
}>();

const difficultyLabel: Record<TourDifficulty, string> = {
    easy: 'Fácil',
    moderate: 'Moderado',
    hard: 'Difícil',
    extreme: 'Extremo',
};

function toggleCategory(slug: string, currentSelected: string | null): void {
    emit('update:category', currentSelected === slug ? null : slug);
}

function handleDifficultyUpdate(value: unknown): void {
    if (
        value === 'all' ||
        value === '' ||
        value === null ||
        value === undefined
    ) {
        emit('update:difficulty', null);

        return;
    }

    if (
        typeof value === 'string' &&
        (TOUR_DIFFICULTIES as string[]).includes(value)
    ) {
        emit('update:difficulty', value as TourDifficulty);
    }
}
</script>

<template>
    <aside class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold tracking-tight text-foreground">
                Filtros
            </h2>
            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                class="h-7 px-2 text-xs"
                @click="$emit('reset')"
            >
                Limpiar
            </Button>
        </div>

        <section class="space-y-3" aria-labelledby="filter-categories">
            <h3
                id="filter-categories"
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Categorías
            </h3>
            <p
                v-if="categories.length === 0"
                class="text-xs text-muted-foreground"
            >
                No hay categorías disponibles.
            </p>
            <div v-else class="flex flex-wrap gap-2">
                <CategoryChip
                    v-for="category in categories"
                    :key="category.id"
                    :label="category.name"
                    :count="category.tours_count"
                    :selected="selectedCategory === category.slug"
                    :icon="category.icon"
                    @select="toggleCategory(category.slug, selectedCategory)"
                />
            </div>
        </section>

        <Separator />

        <section class="space-y-2" aria-labelledby="filter-difficulty">
            <Label
                id="filter-difficulty"
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Dificultad
            </Label>
            <Select
                :model-value="selectedDifficulty ?? 'all'"
                @update:model-value="handleDifficultyUpdate"
            >
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Cualquier dificultad" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Cualquier dificultad</SelectItem>
                    <SelectItem
                        v-for="difficulty in TOUR_DIFFICULTIES"
                        :key="difficulty"
                        :value="difficulty"
                    >
                        {{ difficultyLabel[difficulty] }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </section>

        <Separator />

        <PriceRangeFilter
            :min="priceMin"
            :max="priceMax"
            @update:min="(value) => emit('update:priceMin', value)"
            @update:max="(value) => emit('update:priceMax', value)"
        />
    </aside>
</template>
