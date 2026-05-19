<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Mail, MapPin, Phone } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { useTenant } from '@/composables/useTenant';
import { useTenantBranding } from '@/composables/useTenantBranding';
import { login, register } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';

const { displayName, tenant, configuration } = useTenant();

useTenantBranding();

const hasSocialLinks = computed(() => {
    const links = configuration.value?.social_links;

    if (!links) {
        return false;
    }

    return Object.values(links).some((value) => Boolean(value));
});
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
                    href="/"
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
                        <Link
                            href="/account/bookings"
                            class="hidden text-sm font-medium text-muted-foreground transition hover:text-foreground sm:inline-flex"
                        >
                            Reservas
                        </Link>
                        <Button as-child variant="outline" size="sm">
                            <Link href="/account">Mi cuenta</Link>
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
        <footer class="bg-[#2B3B2E] text-white">
            <div
                class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-3 lg:px-8"
            >
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold tracking-wider uppercase">
                        Información de Contacto
                    </h3>
                    <ul class="space-y-3 text-sm text-white/70">
                        <li class="flex items-start gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            <span>{{
                                configuration?.contact_info?.address ??
                                'Calle 123, Siempre Viva'
                            }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <Phone class="size-4 shrink-0" />
                            <span>{{
                                configuration?.contact_info?.phone ??
                                '+57 3009910019'
                            }}</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <Mail class="size-4 shrink-0" />
                            <span>{{
                                configuration?.contact_info?.email ??
                                'contacto@ecotravel.com'
                            }}</span>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="hasSocialLinks"
                    class="space-y-4 sm:col-start-2 lg:col-start-3"
                >
                    <h3 class="text-sm font-semibold tracking-wider uppercase">
                        Síguenos en Redes Sociales
                    </h3>
                    <div class="flex items-center gap-4">
                        <a
                            v-if="configuration?.social_links?.facebook"
                            :href="configuration.social_links.facebook"
                            target="_blank"
                            rel="noopener"
                            aria-label="Facebook"
                            class="text-white/70 transition hover:text-white"
                        >
                            <svg
                                class="size-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"
                                />
                            </svg>
                        </a>
                        <a
                            v-if="configuration?.social_links?.instagram"
                            :href="configuration.social_links.instagram"
                            target="_blank"
                            rel="noopener"
                            aria-label="Instagram"
                            class="text-white/70 transition hover:text-white"
                        >
                            <svg
                                class="size-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123s-.012 3.056-.06 4.122c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06s-3.056-.012-4.122-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"
                                />
                            </svg>
                        </a>
                        <a
                            v-if="configuration?.social_links?.twitter"
                            :href="configuration.social_links.twitter"
                            target="_blank"
                            rel="noopener"
                            aria-label="Twitter"
                            class="text-white/70 transition hover:text-white"
                        >
                            <svg
                                class="size-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"
                                />
                            </svg>
                        </a>
                        <a
                            v-if="configuration?.social_links?.youtube"
                            :href="configuration.social_links.youtube"
                            target="_blank"
                            rel="noopener"
                            aria-label="YouTube"
                            class="text-white/70 transition hover:text-white"
                        >
                            <svg
                                class="size-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"
                                />
                            </svg>
                        </a>
                        <a
                            v-if="configuration?.social_links?.tiktok"
                            :href="configuration.social_links.tiktok"
                            target="_blank"
                            rel="noopener"
                            aria-label="TikTok"
                            class="text-white/70 transition hover:text-white"
                        >
                            <svg
                                class="size-5"
                                fill="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V9.05a8.16 8.16 0 0 0 4.93 1.66V7.27a4.85 4.85 0 0 1-2-.58z"
                                />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10">
                <div
                    class="mx-auto flex w-full max-w-7xl items-center justify-center px-4 py-4 text-xs text-white/50 sm:px-6 lg:px-8"
                >
                    <span
                        >&copy; {{ new Date().getFullYear() }}
                        {{ displayName }}</span
                    >
                </div>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
