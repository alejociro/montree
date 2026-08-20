<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';
import { routeColor } from '@/lib/tour-route';

defineProps<{
    hasPickup: boolean;
    hasSites: boolean;
    hasDrop: boolean;
    directionsUrl: string | null;
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-[18px] gap-y-2 border-t border-border px-[18px] py-3.5 text-xs text-muted-foreground lg:col-span-2"
    >
        <span v-if="hasPickup" class="flex items-center gap-2">
            <i
                class="size-2.5 rounded-full"
                :style="{ background: routeColor('pickup') }"
            />
            {{ $t('Recogida') }}
        </span>
        <span v-if="hasSites" class="flex items-center gap-2">
            <i
                class="size-2.5 rounded-full"
                :style="{ background: routeColor('site') }"
            />
            {{ $t('Paradas del tour') }}
        </span>
        <span v-if="hasDrop" class="flex items-center gap-2">
            <i
                class="size-2.5 rounded-full"
                :style="{ background: routeColor('drop') }"
            />
            {{ $t('Regreso') }}
        </span>
        <span v-if="hasPickup && hasSites" class="flex items-center gap-2">
            <i
                class="w-[22px] border-t-2 border-dashed"
                :style="{ borderColor: routeColor('transfer') }"
            />
            {{ $t('Traslado en vehículo') }}
        </span>
        <span v-if="hasSites" class="flex items-center gap-2">
            <i
                class="w-[22px] border-t-[3px]"
                :style="{ borderColor: routeColor('pickup') }"
            />
            {{ $t('Recorrido a pie') }}
        </span>
        <a
            v-if="directionsUrl"
            :href="directionsUrl"
            target="_blank"
            rel="noopener"
            class="ml-auto flex items-center gap-1.5 font-medium text-primary transition hover:underline"
        >
            {{ $t('Abrir indicaciones') }}
            <ExternalLink class="size-3.5" />
        </a>
    </div>
</template>
