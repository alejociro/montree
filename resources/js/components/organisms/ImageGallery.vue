<script setup lang="ts">
import { ref } from 'vue';
import type { TourDetailImage } from '@/types/tour-detail';

const props = defineProps<{ images: TourDetailImage[]; tourName: string }>();
const activeIndex = ref(0);
const active = () => props.images[activeIndex.value] ?? null;
</script>

<template>
    <div v-if="images.length > 0" class="space-y-3">
        <div class="aspect-[16/9] w-full overflow-hidden rounded-xl bg-muted">
            <img
                v-if="active()?.url"
                :src="active()!.url!"
                :alt="active()?.alt_text ?? tourName"
                class="h-full w-full object-cover"
            />
        </div>
        <div
            v-if="images.length > 1"
            class="flex snap-x snap-mandatory gap-2 overflow-x-auto pb-2"
        >
            <button
                v-for="(img, i) in images"
                :key="img.id"
                type="button"
                class="aspect-square w-20 flex-none snap-start overflow-hidden rounded-md border-2 transition"
                :class="
                    i === activeIndex
                        ? 'border-primary'
                        : 'border-transparent opacity-70 hover:opacity-100'
                "
                @click="activeIndex = i"
            >
                <img
                    v-if="img.url"
                    :src="img.url"
                    :alt="img.alt_text ?? tourName"
                    class="h-full w-full object-cover"
                />
            </button>
        </div>
    </div>
</template>
