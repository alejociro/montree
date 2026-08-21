<script setup lang="ts">
import { ExternalLink, Images } from 'lucide-vue-next';
import { computed } from 'vue';
import MonoLabel from '@/components/atoms/MonoLabel.vue';
import TourStatusBadge from '@/components/organisms/TourStatusBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/composables/useTranslations';
import type { TourImage, TourStatus } from '@/types/tour';

const { t } = useTranslations();

/**
 * Columna de contexto del handoff: en qué estado está el tour, adónde se va a
 * ver y qué portada tiene.
 *
 * WHY: acompañaba solo a la pestaña «Contenido» y desaparecía al pasar a
 * «Salidas» o «Pasajeros», que es justo donde hace falta recordar si el tour
 * está publicado antes de abrir una salida a la venta.
 */
const props = defineProps<{
    status: TourStatus;
    publicUrl: string;
    images: TourImage[];
}>();

const explanation: Record<TourStatus, string> = {
    draft: t('El tour no aparece en el catálogo público hasta publicarlo.'),
    active: t('El tour está visible en el catálogo público.'),
    paused: t(
        'El tour no admite reservas nuevas, pero conserva las que tiene.',
    ),
    archived: t('El tour está fuera del catálogo y de los listados internos.'),
};

const cover = computed(
    () =>
        props.images.find((image) => image.is_cover) ?? props.images[0] ?? null,
);

const rest = computed(() =>
    props.images.filter((image) => image.id !== cover.value?.id).slice(0, 3),
);
</script>

<template>
    <Card>
        <CardContent class="space-y-3">
            <MonoLabel>{{ $t('Estado') }}</MonoLabel>

            <div class="flex items-center gap-2">
                <TourStatusBadge :status="props.status" />
            </div>

            <p class="text-sm text-muted-foreground">
                {{ explanation[props.status] }}
            </p>

            <a
                :href="props.publicUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-[13px] font-medium text-primary-readable hover:underline"
            >
                <ExternalLink class="size-3.5" />
                {{ $t('Ver en el catálogo') }}
            </a>

            <div class="border-t border-brand-line-2 pt-3">
                <MonoLabel>{{ $t('Galería') }}</MonoLabel>

                <p
                    v-if="props.images.length === 0"
                    class="mt-2 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Images class="size-4" />
                    {{ $t('Sin imágenes todavía.') }}
                </p>

                <div v-else class="mt-2 grid grid-cols-3 gap-2">
                    <div
                        v-if="cover"
                        class="relative col-span-3 overflow-hidden rounded-lg border border-border"
                    >
                        <img
                            :src="cover.url"
                            :alt="cover.alt_text ?? ''"
                            class="h-24 w-full object-cover"
                        />
                        <span
                            class="absolute bottom-1.5 left-1.5 rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold text-secondary-foreground"
                        >
                            {{ $t('Portada') }}
                        </span>
                    </div>
                    <img
                        v-for="image in rest"
                        :key="image.id"
                        :src="image.url"
                        :alt="image.alt_text ?? ''"
                        class="h-14 w-full rounded-lg border border-border object-cover"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
