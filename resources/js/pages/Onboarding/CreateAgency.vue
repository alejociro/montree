<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, CalendarCheck, Leaf, Palette } from 'lucide-vue-next';
import AgencySignupForm from '@/components/organisms/AgencySignupForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { home, login } from '@/routes';

const { t } = useTranslations();

const highlights = [
    {
        icon: CalendarCheck,
        title: t('Reservas 24/7'),
        body: t(
            'Recibe y confirma reservas automáticamente, sin responder un solo WhatsApp.',
        ),
    },
    {
        icon: BarChart3,
        title: t('Tu negocio en tiempo real'),
        body: t(
            'Ingresos, cupos y próximas salidas en un panel claro y accionable.',
        ),
    },
    {
        icon: Palette,
        title: t('Tu marca, tu subdominio'),
        body: t(
            'Un sitio propio con tu logo y colores desde el primer minuto.',
        ),
    },
];
</script>

<template>
    <div class="min-h-svh bg-background text-foreground lg:grid lg:grid-cols-2">
        <Head :title="$t('Crea tu agencia — Montree')" />

        <aside
            class="relative hidden overflow-hidden bg-primary px-10 py-12 text-brand-green-100 lg:flex lg:flex-col lg:justify-between"
        >
            <div
                class="absolute inset-0 opacity-40"
                aria-hidden="true"
                style="
                    background-image: radial-gradient(
                        circle,
                        rgba(255, 255, 255, 0.08) 1px,
                        transparent 1px
                    );
                    background-size: 26px 26px;
                "
            />

            <Link
                :href="home().url"
                class="relative z-10 inline-flex items-center gap-2"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                >
                    <Leaf class="size-5" />
                </span>
                <span class="text-lg font-semibold tracking-tight">{{
                    $t('Montree')
                }}</span>
            </Link>

            <div class="relative z-10 max-w-md space-y-8">
                <h1
                    class="text-3xl font-semibold tracking-tight text-white sm:text-4xl"
                >
                    {{
                        $t('Pon tu agencia de ecoturismo en línea en minutos.')
                    }}
                </h1>

                <ul class="space-y-5">
                    <li
                        v-for="item in highlights"
                        :key="item.title"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/20 text-brand-green-100"
                        >
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <div>
                            <p class="font-medium text-white">
                                {{ item.title }}
                            </p>
                            <p class="text-sm text-brand-green-100">
                                {{ item.body }}
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="relative z-10 text-sm text-brand-green-100">
                {{
                    $t(
                        'Sin tarjeta de crédito · 14 días de prueba en el plan Professional.',
                    )
                }}
            </p>
        </aside>

        <main
            class="flex min-h-svh flex-col justify-center px-6 py-10 sm:px-10 lg:px-16"
        >
            <div class="mx-auto w-full max-w-md">
                <Link
                    :href="home().url"
                    class="mb-8 inline-flex items-center gap-2 lg:hidden"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <Leaf class="size-4" />
                    </span>
                    <span class="text-base font-semibold tracking-tight">{{
                        $t('Montree')
                    }}</span>
                </Link>

                <div class="mb-8 space-y-2">
                    <h2 class="text-2xl font-semibold tracking-tight">
                        {{ $t('Crea tu agencia') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            $t(
                                'Completa tus datos y empieza tu prueba gratis. Activas tu cuenta confirmando tu email.',
                            )
                        }}
                    </p>
                </div>

                <AgencySignupForm />

                <p class="mt-6 text-center text-sm text-muted-foreground">
                    {{ $t('¿Ya tienes una agencia?') }}
                    <Link
                        :href="login().url"
                        class="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {{ $t('Inicia sesión') }}
                    </Link>
                </p>
            </div>
        </main>
    </div>
</template>
