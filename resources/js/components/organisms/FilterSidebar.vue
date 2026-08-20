<script setup lang="ts">
import { Check } from 'lucide-vue-next';
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
import { useTranslations } from '@/composables/useTranslations';
import type { CatalogCategory } from '@/types/catalog';
import { TOUR_DIFFICULTIES } from '@/types/tour';
import type { TourDifficulty } from '@/types/tour';

const { t } = useTranslations();

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
    easy: t('Fácil'),
    moderate: t('Moderado'),
    hard: t('Difícil'),
    extreme: t('Extremo'),
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
        <h2 class="text-lg font-semibold tracking-tight text-foreground">
            {{ $t('Filtros') }}
        </h2>

        <section class="space-y-3" aria-labelledby="filter-categories">
            <h3
                id="filter-categories"
                class="text-sm font-medium text-foreground"
            >
                {{ $t('Categorías') }}
            </h3>
            <p
                v-if="categories.length === 0"
                class="text-xs text-muted-foreground"
            >
                {{ $t('No hay categorías disponibles.') }}
            </p>
            <ul v-else class="space-y-1">
                <li v-for="category in categories" :key="category.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                        :class="
                            selectedCategory === category.slug
                                ? 'font-medium text-foreground'
                                : 'text-muted-foreground'
                        "
                        :aria-pressed="selectedCategory === category.slug"
                        @click="toggleCategory(category.slug, selectedCategory)"
                    >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded-full border transition"
                            :class="
                                selectedCategory === category.slug
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border'
                            "
                            aria-hidden="true"
                        >
                            <Check
                                v-if="selectedCategory === category.slug"
                                class="size-3"
                            />
                        </span>
                        <span class="flex-1 truncate">{{ category.name }}</span>
                        <span
                            v-if="category.tours_count > 0"
                            class="text-xs text-muted-foreground"
                        >
                            {{ category.tours_count }}
                        </span>
                    </button>
                </li>
            </ul>
        </section>

        <Separator />

        <section class="space-y-2" aria-labelledby="filter-difficulty">
            <Label id="filter-difficulty" class="text-sm font-medium">
                {{ $t('Dificultad') }}
            </Label>
            <Select
                :model-value="selectedDifficulty ?? 'all'"
                @update:model-value="handleDifficultyUpdate"
            >
                <SelectTrigger class="w-full">
                    <SelectValue :placeholder="$t('Cualquier dificultad')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        $t('Cualquier dificultad')
                    }}</SelectItem>
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

        <Button
            type="button"
            class="w-full"
            :disabled="!hasActiveFilters"
            @click="emit('reset')"
        >
            {{ $t('Limpiar filtros') }}
        </Button>
    </aside>
</template>
