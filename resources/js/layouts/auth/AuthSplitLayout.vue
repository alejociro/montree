<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TenantBrandedLogo from '@/components/atoms/TenantBrandedLogo.vue';
import { useTenant } from '@/composables/useTenant';
import { useTenantBranding } from '@/composables/useTenantBranding';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

useTenantBranding();

const { configuration, displayName } = useTenant();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-primary p-10 text-primary-foreground lg:flex dark:border-r"
        >
            <Link
                :href="home()"
                class="relative z-20 flex items-center gap-2 text-lg font-medium"
            >
                <TenantBrandedLogo size="sm" class="text-primary-foreground" />
                <span>{{ displayName }}</span>
            </Link>
            <div v-if="configuration?.tagline" class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg">
                        &ldquo;{{ configuration.tagline }}&rdquo;
                    </p>
                </blockquote>
            </div>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
