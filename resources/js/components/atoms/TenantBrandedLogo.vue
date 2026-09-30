<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useTenant } from '@/composables/useTenant';
import { cn } from '@/lib/utils';

type LogoSize = 'sm' | 'md' | 'lg';

type Props = {
    size?: LogoSize;
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const { configuration, displayName, isResolved } = useTenant();

const imageSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-8';
        case 'lg':
            return 'h-16';
        case 'md':
        default:
            return 'h-12';
    }
});

const iconSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-7';
        case 'lg':
            return 'size-12';
        case 'md':
        default:
            return 'size-9';
    }
});

const nameSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-base';
        case 'lg':
            return 'text-2xl';
        case 'md':
        default:
            return 'text-xl';
    }
});

const logoUrl = computed(() => configuration.value?.logo_url ?? null);

/**
 * WHY: un logo borrado del disco (o una URL vieja en caché) dejaba el header
 * vacío, sin nombre de agencia ni icono. El `@error` del `<img>` degrada al
 * nombre en vez de a un hueco.
 */
const logoFailed = ref(false);

watch(logoUrl, () => {
    logoFailed.value = false;
});

const hasLogo = computed(
    () => isResolved.value && logoUrl.value !== null && !logoFailed.value,
);

const showNameFallback = computed(() => isResolved.value && !hasLogo.value);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex min-w-0 items-center justify-center gap-2',
                props.class,
            )
        "
    >
        <img
            v-if="hasLogo"
            :src="logoUrl ?? undefined"
            :alt="displayName"
            :class="cn('w-auto object-contain', imageSizeClass)"
            @error="logoFailed = true"
        />
        <span
            v-else-if="showNameFallback"
            :class="
                cn(
                    'max-w-full truncate font-semibold tracking-tight text-primary-readable',
                    nameSizeClass,
                )
            "
        >
            {{ displayName }}
        </span>
        <AppLogoIcon
            v-else
            :class="cn('fill-current text-foreground', iconSizeClass)"
        />
    </span>
</template>
