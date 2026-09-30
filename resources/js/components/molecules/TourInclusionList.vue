<script setup lang="ts">
defineProps<{
    includes: string[];
    excludes: string[];
}>();
</script>

<template>
    <div class="mt-3.5 grid gap-x-[34px] gap-y-6 lg:grid-cols-2">
        <div v-if="includes.length > 0" class="min-w-0">
            <h3
                class="text-[13px] font-semibold tracking-[0.02em] text-foreground"
            >
                {{ $t('Incluye') }}
            </h3>
            <ul class="mt-1">
                <li
                    v-for="(item, index) in includes"
                    :key="`include-${index}`"
                    class="flex items-start gap-2.5 border-b border-dashed border-border py-2 text-sm last:border-b-0"
                >
                    <span
                        class="mt-0.5 grid size-[18px] flex-none place-items-center rounded-full bg-secondary-soft text-[11px] font-bold text-secondary-readable"
                        aria-hidden="true"
                    >
                        ✓
                    </span>
                    <span class="sr-only">{{ $t('Incluido') }}:</span>
                    {{ item }}
                </li>
            </ul>
        </div>

        <div v-if="excludes.length > 0" class="min-w-0">
            <h3
                class="text-[13px] font-semibold tracking-[0.02em] text-foreground"
            >
                {{ $t('No incluye') }}
            </h3>
            <ul class="mt-1">
                <li
                    v-for="(item, index) in excludes"
                    :key="`exclude-${index}`"
                    class="flex items-start gap-2.5 border-b border-dashed border-border py-2 text-sm last:border-b-0"
                >
                    <span
                        class="mt-0.5 grid size-[18px] flex-none place-items-center rounded-full bg-brand-drop/15 text-[11px] font-bold text-brand-drop"
                        aria-hidden="true"
                    >
                        ×
                    </span>
                    <span class="sr-only">{{ $t('No incluido') }}:</span>
                    {{ item }}
                </li>
            </ul>
        </div>
    </div>
</template>
