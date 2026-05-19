<script setup lang="ts">
import { computed } from 'vue';
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

const hasLogo = computed(
    () => isResolved.value && Boolean(configuration.value?.logo_url),
);

const showNameFallback = computed(
    () => isResolved.value && !configuration.value?.logo_url,
);
</script>

<template>
    <span
        :class="
            cn('inline-flex items-center justify-center gap-2', props.class)
        "
    >
        <img
            v-if="hasLogo"
            :src="configuration?.logo_url ?? undefined"
            :alt="displayName"
            :class="cn('w-auto object-contain', imageSizeClass)"
        />
        <span
            v-else-if="showNameFallback"
            :class="
                cn('font-semibold tracking-tight text-primary', nameSizeClass)
            "
        >
            {{ displayName }}
        </span>
        <AppLogoIcon
            v-else
            :class="
                cn(
                    'fill-current text-foreground dark:text-white',
                    iconSizeClass,
                )
            "
        />
    </span>
</template>
