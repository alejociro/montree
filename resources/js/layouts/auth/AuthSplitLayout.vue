<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Leaf } from 'lucide-vue-next';
import TenantBrandedLogo from '@/components/atoms/TenantBrandedLogo.vue';
import { useTenant } from '@/composables/useTenant';
import { useTenantBranding } from '@/composables/useTenantBranding';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

useTenantBranding();

const { configuration, displayName, isResolved } = useTenant();
</script>

<template>
    <div
        class="relative grid min-h-dvh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <!-- Left panel: branded hero -->
        <div
            class="relative hidden h-full flex-col overflow-hidden lg:flex"
            :class="configuration?.hero_image_url ? 'bg-black' : 'bg-[#2B3B2E]'"
        >
            <!-- Background image with overlay -->
            <img
                v-if="configuration?.hero_image_url"
                :src="configuration.hero_image_url"
                :alt="displayName"
                class="absolute inset-0 h-full w-full object-cover opacity-60"
            />
            <div
                v-else
                class="absolute inset-0 bg-gradient-to-br from-[#2B3B2E] via-[#3A5240] to-[#2B3B2E]"
            />

            <!-- Decorative pattern -->
            <div class="absolute inset-0 opacity-10">
                <div
                    class="absolute -top-20 -left-20 size-96 rounded-full border border-white/20"
                />
                <div
                    class="absolute -right-20 -bottom-32 size-[500px] rounded-full border border-white/20"
                />
                <div
                    class="absolute top-1/3 left-1/3 size-64 rounded-full border border-white/10"
                />
            </div>

            <!-- Content overlay -->
            <div
                class="relative z-10 flex h-full flex-col justify-between p-10 text-white"
            >
                <!-- Top: Logo -->
                <Link :href="home()" class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-white/15 backdrop-blur-sm"
                    >
                        <Leaf class="size-5 text-white" />
                    </span>
                    <span class="text-xl font-semibold tracking-tight">
                        {{ displayName }}
                    </span>
                </Link>

                <!-- Center: Tagline -->
                <div class="space-y-4">
                    <p
                        v-if="configuration?.tagline"
                        class="max-w-md text-3xl leading-tight font-bold"
                    >
                        {{ configuration.tagline }}
                    </p>
                    <p v-else class="max-w-md text-3xl leading-tight font-bold">
                        Descubre experiencias únicas en la naturaleza
                    </p>
                    <p
                        v-if="configuration?.description"
                        class="max-w-sm text-sm leading-relaxed text-white/70"
                    >
                        {{ configuration.description }}
                    </p>
                </div>

                <!-- Bottom: Trust indicators -->
                <div class="flex items-center gap-6 text-sm text-white/60">
                    <span class="flex items-center gap-1.5">
                        <svg
                            class="size-4"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Reserva segura
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg
                            class="size-4"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Soporte 24/7
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg
                            class="size-4"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        Cancelación flexible
                    </span>
                </div>
            </div>
        </div>

        <!-- Right panel: form -->
        <div class="flex items-center justify-center px-6 py-10 lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[380px]"
            >
                <!-- Mobile logo (hidden on desktop) -->
                <div class="flex flex-col items-center gap-3 lg:hidden">
                    <Link :href="home()">
                        <TenantBrandedLogo size="lg" />
                    </Link>
                </div>

                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        v-if="title"
                        class="text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
