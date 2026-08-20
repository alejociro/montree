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
        class="flex flex-col gap-2 rounded-lg border border-border bg-card p-5 shadow-sm"
    >
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-muted-foreground">{{
                title
            }}</span>
            <component
                :is="icon"
                v-if="icon"
                class="size-5 text-muted-foreground"
            />
        </div>
        <p class="text-2xl font-semibold tracking-tight text-foreground">
            {{ value }}
        </p>
        <div
            v-if="description || trend !== undefined"
            class="flex items-center gap-2 text-xs text-muted-foreground"
        >
            <span
                v-if="trend !== undefined && trend !== null"
                :class="trend >= 0 ? 'text-primary' : 'text-destructive'"
            >
                {{ trend >= 0 ? '+' : '' }}{{ trend }}%
            </span>
            <span v-if="description">{{ description }}</span>
        </div>
    </div>
</template>
