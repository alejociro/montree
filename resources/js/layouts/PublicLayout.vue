<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { useTenant } from '@/composables/useTenant';
import { useTenantBranding } from '@/composables/useTenantBranding';
import { login, register } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';

const { displayName, tenant } = useTenant();

useTenantBranding();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="border-b border-border/60 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8"
            >
                <Link
                    :href="catalogIndex().url"
                    class="flex items-center gap-2"
                    :aria-label="displayName"
                >
                    <span
                        class="flex aspect-square size-9 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    <span
                        class="hidden text-base font-semibold tracking-tight sm:inline"
                    >
                        {{ displayName }}
                    </span>
                </Link>
                <nav class="flex items-center gap-2">
                    <Link
                        :href="catalogIndex().url"
                        class="hidden text-sm font-medium text-muted-foreground transition hover:text-foreground sm:inline-flex"
                    >
                        Tours
                    </Link>
                    <template v-if="$page.props.auth.user">
                        <Button as-child variant="outline" size="sm">
                            <Link href="/dashboard">Mi cuenta</Link>
                        </Button>
                    </template>
                    <template v-else-if="tenant">
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="login().url">Ingresar</Link>
                        </Button>
                        <Button as-child size="sm">
                            <Link :href="register().url">Registrarse</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>
        <main class="flex-1">
            <slot />
        </main>
        <footer class="border-t border-border/60 bg-card/60">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row sm:px-6 lg:px-8"
            >
                <span
                    >&copy; {{ new Date().getFullYear() }}
                    {{ displayName }}</span
                >
                <span>Powered by MONTREE</span>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
