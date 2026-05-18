<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    value: string;
    description?: string;
    icon?: Component;
    trend?: number | null;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
    >
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ title }}</span>
            <component
                :is="icon"
                v-if="icon"
                class="size-5 text-zinc-400 dark:text-zinc-500"
            />
        </div>
        <p class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
            {{ value }}
        </p>
        <div
            v-if="description || trend !== undefined"
            class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400"
        >
            <span
                v-if="trend !== undefined && trend !== null"
                :class="
                    trend >= 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-red-600 dark:text-red-400'
                "
            >
                {{ trend >= 0 ? '+' : '' }}{{ trend }}%
            </span>
            <span v-if="description">{{ description }}</span>
        </div>
    </div>
</template>
