<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TenantBrandedLogo from '@/components/atoms/TenantBrandedLogo.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <TenantBrandedLogo size="md" />
                <span class="sr-only">{{ displayName }}</span>
            </Link>

            <div class="flex flex-col gap-6">
                <Card class="rounded-xl">
                    <CardHeader class="px-10 pt-8 pb-0 text-center">
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <p
                            v-if="configuration?.tagline"
                            class="mt-1 text-sm font-medium text-primary"
                        >
                            {{ configuration.tagline }}
                        </p>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-10 py-8">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
