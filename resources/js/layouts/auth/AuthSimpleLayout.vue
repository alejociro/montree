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
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <TenantBrandedLogo size="md" />
                        <span class="sr-only">{{ displayName }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p
                            v-if="configuration?.tagline"
                            class="text-center text-sm font-medium text-primary"
                        >
                            {{ configuration.tagline }}
                        </p>
                        <p
                            v-if="description"
                            class="text-center text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
