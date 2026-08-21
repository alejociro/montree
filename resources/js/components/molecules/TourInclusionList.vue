<script setup lang="ts">
defineProps<{
    includes: string[];
    excludes: string[];
}>();
</script>

<template>
    <div class="mt-3.5 grid gap-x-[34px] gap-y-3 lg:grid-cols-2">
        <ul v-if="includes.length > 0" class="min-w-0">
            <li
                v-for="(item, index) in includes"
                :key="`include-${index}`"
                class="flex items-start gap-2.5 border-b border-dashed border-border py-2 text-sm last:border-b-0"
            >
                <span
                    class="mt-0.5 grid size-[18px] flex-none place-items-center rounded-full bg-brand-green-100 text-[11px] font-bold text-brand-green"
                    aria-hidden="true"
                >
                    ✓
                </span>
                <span class="sr-only">{{ $t('Incluido') }}:</span>
                {{ item }}
            </li>
        </ul>

        <ul v-if="excludes.length > 0" class="min-w-0">
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
</template>
