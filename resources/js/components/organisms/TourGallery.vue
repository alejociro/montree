<script setup lang="ts">
import { ChevronLeft, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FavoriteButton from '@/components/molecules/FavoriteButton.vue';
import type { TourDetailImage } from '@/types/tour-detail';

const props = defineProps<{
    images: TourDetailImage[];
    tourId: number;
    tourName: string;
    isFavorite: boolean;
    isAuthenticated: boolean;
}>();

/**
 * El handoff arma la galería con tres celdas: la principal ocupa las dos filas
 * y a su derecha van dos secundarias. El resto de fotos viven en el lightbox y
 * se anuncian sobre la última celda.
 */
const heroImage = computed<TourDetailImage | null>(
    () => props.images[0] ?? null,
);
const sideImages = computed(() => props.images.slice(1, 3));
const hiddenCount = computed(() => Math.max(0, props.images.length - 3));

const activeIndex = ref(0);
const lightboxOpen = ref(false);

function openLightbox(index: number): void {
    activeIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox(): void {
    lightboxOpen.value = false;
}

function step(offset: number): void {
    const total = props.images.length;

    activeIndex.value = (activeIndex.value + offset + total) % total;
}

const activeImage = computed<TourDetailImage | null>(
    () => props.images[activeIndex.value] ?? null,
);
</script>

<template>
    <div
        v-if="heroImage"
        class="grid gap-2.5 sm:grid-cols-[2fr_1fr] sm:grid-rows-[150px_150px]"
    >
        <div class="relative sm:row-span-2">
            <button
                type="button"
                class="aspect-[16/10] w-full overflow-hidden rounded-2xl border border-border bg-muted sm:aspect-auto sm:h-full"
                :aria-label="$t('Ampliar foto')"
                @click="openLightbox(0)"
            >
                <img
                    v-if="heroImage.url"
                    :src="heroImage.url"
                    :alt="heroImage.alt_text ?? tourName"
                    class="size-full object-cover transition-transform duration-300 hover:scale-105"
                />
            </button>
            <FavoriteButton
                v-if="isAuthenticated"
                :tour-id="tourId"
                :initial-favorite="isFavorite"
                class="absolute top-3 right-3 size-[34px] rounded-full border border-border bg-card/80 text-foreground backdrop-blur-sm hover:bg-card"
            />
        </div>

        <button
            v-for="(image, index) in sideImages"
            :key="image.id"
            type="button"
            class="relative aspect-[16/10] overflow-hidden rounded-2xl border border-border bg-muted sm:aspect-auto"
            :aria-label="$t('Ampliar foto')"
            @click="openLightbox(index + 1)"
        >
            <img
                v-if="image.url"
                :src="image.url"
                :alt="image.alt_text ?? tourName"
                class="size-full object-cover transition-transform duration-300 hover:scale-105"
            />
            <span
                v-if="index === sideImages.length - 1 && hiddenCount > 0"
                class="absolute inset-0 grid place-items-center bg-brand-ink/55 text-sm font-semibold text-brand-cream"
            >
                {{ $t('+:count fotos', { count: hiddenCount }) }}
            </span>
        </button>
    </div>

    <Teleport to="body">
        <div
            v-if="lightboxOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            @click.self="closeLightbox"
            @keydown.escape="closeLightbox"
        >
            <button
                type="button"
                class="absolute top-4 right-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                :aria-label="$t('Cerrar')"
                @click="closeLightbox"
            >
                <X class="size-6" />
            </button>

            <button
                v-if="images.length > 1"
                type="button"
                class="absolute left-4 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20"
                :aria-label="$t('Anterior')"
                @click="step(-1)"
            >
                <ChevronLeft class="size-6" />
            </button>

            <div class="max-h-[85vh] max-w-[90vw]">
                <img
                    v-if="activeImage?.url"
                    :src="activeImage.url"
                    :alt="activeImage.alt_text ?? tourName"
                    class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain"
                />
            </div>

            <button
                v-if="images.length > 1"
                type="button"
                class="absolute right-4 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20"
                :aria-label="$t('Siguiente')"
                @click="step(1)"
            >
                <ChevronLeft class="size-6 rotate-180" />
            </button>

            <div
                v-if="images.length > 1"
                class="absolute bottom-4 left-1/2 flex -translate-x-1/2 gap-2"
            >
                <button
                    v-for="(image, index) in images"
                    :key="image.id"
                    type="button"
                    class="size-2.5 rounded-full transition"
                    :class="
                        index === activeIndex
                            ? 'bg-white'
                            : 'bg-white/40 hover:bg-white/70'
                    "
                    :aria-label="$t('Ver foto :number', { number: index + 1 })"
                    @click="activeIndex = index"
                />
            </div>
        </div>
    </Teleport>
</template>
