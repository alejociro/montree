<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import TrendIndicator from '@/components/molecules/TrendIndicator.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    title: string;
    value: string;
    description?: string;
    icon?: LucideIcon;
    trend?: number | null;
    trendLabel?: string;
};

defineProps<Props>();
</script>

<template>
    <Card class="overflow-hidden">
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
        >
            <CardTitle class="text-sm font-medium text-muted-foreground">
                {{ title }}
            </CardTitle>
            <component
                v-if="icon"
                :is="icon"
                class="size-4 text-muted-foreground"
            />
        </CardHeader>
        <CardContent>
            <div class="text-2xl font-semibold tracking-tight">{{ value }}</div>
            <CardDescription class="mt-1 flex items-center gap-2 text-xs">
                <TrendIndicator
                    v-if="trend !== undefined"
                    :value="trend ?? null"
                    :label="trendLabel"
                />
                <span v-if="description" class="text-muted-foreground">
                    {{ description }}
                </span>
            </CardDescription>
        </CardContent>
    </Card>
</template>
