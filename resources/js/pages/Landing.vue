<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Calendar,
    CheckCircle,
    CreditCard,
    Globe,
    Leaf,
    Mail,
    Mountain,
    Palette,
    Shield,
    Smartphone,
    Sparkles,
    Star,
    Users,
    Zap,
} from 'lucide-vue-next';

const features = [
    {
        icon: Mountain,
        title: 'Catálogo de tours',
        description: 'Publica y gestiona tus experiencias con fotos, itinerarios, precios y fechas disponibles.',
    },
    {
        icon: Calendar,
        title: 'Reservas en línea',
        description: 'Tus clientes reservan y pagan directamente desde tu sitio. Sin intermediarios.',
    },
    {
        icon: Palette,
        title: 'Tu marca, tu estilo',
        description: 'Personaliza colores, logo, dominio y contenido. Tu agencia, tu identidad.',
    },
    {
        icon: Users,
        title: 'Gestión de equipo',
        description: 'Asigna roles a guías, operadores y administradores con permisos específicos.',
    },
    {
        icon: BarChart3,
        title: 'Métricas en tiempo real',
        description: 'Dashboard con reservas, ingresos, clientes y rendimiento de cada tour.',
    },
    {
        icon: CreditCard,
        title: 'Pagos integrados',
        description: 'Cobra con Stripe de forma segura. Comisiones claras, sin sorpresas.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Regístrate',
        description: 'Crea tu cuenta en minutos. Sin tarjeta de crédito, sin compromisos.',
    },
    {
        number: '02',
        title: 'Personaliza',
        description: 'Sube tu logo, elige tus colores y configura tu dominio personalizado.',
    },
    {
        number: '03',
        title: 'Publica tus tours',
        description: 'Agrega experiencias con fotos, itinerarios, precios y fechas disponibles.',
    },
    {
        number: '04',
        title: 'Recibe reservas',
        description: 'Tus clientes reservan y pagan en línea. Tú gestionas todo desde un solo lugar.',
    },
];

const benefits = [
    { icon: Zap, text: 'Listo en menos de 10 minutos' },
    { icon: Globe, text: 'Dominio personalizado incluido' },
    { icon: Smartphone, text: 'Diseño responsive para móviles' },
    { icon: Shield, text: 'Pagos seguros con Stripe' },
    { icon: Star, text: 'Sistema de reseñas integrado' },
    { icon: Mail, text: 'Newsletter y notificaciones' },
];
</script>

<template>
    <Head title="Montree — Automatiza tu agencia de ecoturismo" />

    <div class="min-h-screen bg-white text-zinc-900 antialiased">
        <!-- Header -->
        <header class="sticky top-0 z-50 border-b border-zinc-100 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <Leaf class="size-5" />
                    </div>
                    <span class="text-xl font-bold tracking-tight text-zinc-900">Montree</span>
                </div>
                <nav class="hidden items-center gap-8 md:flex">
                    <a href="#features" class="text-sm font-medium text-zinc-600 transition hover:text-zinc-900">Funciones</a>
                    <a href="#how-it-works" class="text-sm font-medium text-zinc-600 transition hover:text-zinc-900">Cómo funciona</a>
                    <a href="#benefits" class="text-sm font-medium text-zinc-600 transition hover:text-zinc-900">Beneficios</a>
                </nav>
                <div class="flex items-center gap-3">
                    <Link
                        href="/login"
                        class="hidden text-sm font-medium text-zinc-600 transition hover:text-zinc-900 sm:inline-flex"
                    >
                        Iniciar sesión
                    </Link>
                    <a
                        href="#contact"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        Comenzar gratis
                    </a>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden bg-gradient-to-br from-emerald-50 via-white to-teal-50">
            <div class="absolute inset-0 opacity-30">
                <div class="absolute -left-20 top-20 size-72 rounded-full bg-emerald-200 blur-3xl" />
                <div class="absolute -right-20 bottom-20 size-96 rounded-full bg-teal-200 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8 lg:py-40">
                <div class="mx-auto max-w-3xl text-center">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-sm font-medium text-emerald-700">
                        <Sparkles class="size-4" />
                        Plataforma para agencias de ecoturismo
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-tight text-zinc-900 sm:text-5xl lg:text-6xl">
                        Automatiza tu agencia de
                        <span class="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">ecoturismo</span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-zinc-600 sm:text-xl">
                        Crea tu sitio web personalizado, publica tus tours, recibe reservas y pagos en línea.
                        Todo lo que necesitas para digitalizar tu agencia en un solo lugar.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <a
                            href="#contact"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-700 hover:shadow-xl"
                        >
                            Crear mi agencia
                            <ArrowRight class="size-5" />
                        </a>
                        <a
                            href="#how-it-works"
                            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-8 py-3.5 text-base font-semibold text-zinc-700 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50"
                        >
                            Ver cómo funciona
                        </a>
                    </div>
                </div>

                <!-- Dashboard mockup -->
                <div class="relative mx-auto mt-16 max-w-5xl">
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl shadow-zinc-200/50">
                        <div class="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50 px-4 py-3">
                            <div class="size-3 rounded-full bg-red-400" />
                            <div class="size-3 rounded-full bg-yellow-400" />
                            <div class="size-3 rounded-full bg-green-400" />
                            <div class="ml-4 flex-1 rounded-md bg-zinc-200/60 px-4 py-1.5 text-center text-xs text-zinc-400">
                                tuagencia.montree.co
                            </div>
                        </div>
                        <div class="bg-gradient-to-b from-emerald-600 to-emerald-700 p-8 sm:p-12">
                            <div class="mx-auto max-w-2xl text-center text-white">
                                <h3 class="text-2xl font-bold sm:text-3xl">Eco Aventura Colombia</h3>
                                <p class="mt-2 text-emerald-100">Descubre las mejores experiencias de ecoturismo</p>
                            </div>
                            <div class="mx-auto mt-8 grid max-w-3xl gap-4 sm:grid-cols-3">
                                <div
                                    v-for="tour in ['Senderismo Cocora', 'Avistamiento de ballenas', 'Río Claro']"
                                    :key="tour"
                                    class="overflow-hidden rounded-xl bg-white/10 p-4 backdrop-blur-sm"
                                >
                                    <div class="mb-3 h-24 rounded-lg bg-white/20" />
                                    <p class="text-sm font-semibold text-white">{{ tour }}</p>
                                    <p class="mt-1 text-xs text-emerald-200">Desde $120.000 COP</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -bottom-4 left-1/2 h-8 w-3/4 -translate-x-1/2 rounded-2xl bg-zinc-900/5 blur-xl" />
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="bg-white py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                        Todo lo que tu agencia necesita
                    </h2>
                    <p class="mt-4 text-lg text-zinc-600">
                        Herramientas diseñadas específicamente para agencias de ecoturismo y turismo de aventura.
                    </p>
                </div>
                <div class="mx-auto mt-16 grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="group rounded-2xl border border-zinc-100 bg-white p-6 transition hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-50"
                    >
                        <div class="mb-4 inline-flex rounded-xl bg-emerald-50 p-3 text-emerald-600 transition group-hover:bg-emerald-100">
                            <component :is="feature.icon" class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900">{{ feature.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ feature.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="bg-zinc-50 py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                        Cómo funciona
                    </h2>
                    <p class="mt-4 text-lg text-zinc-600">
                        En cuatro pasos simples tendrás tu agencia digital lista para recibir clientes.
                    </p>
                </div>
                <div class="mx-auto mt-16 grid max-w-4xl gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="step in steps"
                        :key="step.number"
                        class="relative text-center"
                    >
                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-emerald-600 text-xl font-bold text-white shadow-lg shadow-emerald-200">
                            {{ step.number }}
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ step.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Customization showcase -->
        <section class="bg-white py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                            Personalización total de tu marca
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-zinc-600">
                            Cada agencia es única. Montree te permite personalizar cada aspecto de tu sitio para que refleje la identidad de tu negocio.
                        </p>
                        <ul class="mt-8 space-y-4">
                            <li
                                v-for="item in [
                                    'Logo, colores y tipografía de tu marca',
                                    'Dominio personalizado (tuagencia.montree.co)',
                                    'Imágenes de portada y hero personalizadas',
                                    'Información de contacto y redes sociales',
                                    'Moneda, idioma y zona horaria local',
                                ]"
                                :key="item"
                                class="flex items-start gap-3"
                            >
                                <CheckCircle class="mt-0.5 size-5 shrink-0 text-emerald-500" />
                                <span class="text-zinc-700">{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                            <div class="mb-6 flex items-center gap-3">
                                <div class="size-10 rounded-lg bg-emerald-600" />
                                <div>
                                    <div class="h-4 w-32 rounded bg-zinc-200" />
                                    <div class="mt-1.5 h-3 w-24 rounded bg-zinc-100" />
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-medium text-zinc-500">Color primario</label>
                                    <div class="mt-1 flex items-center gap-3">
                                        <div class="size-8 rounded-lg bg-emerald-600 ring-2 ring-emerald-300 ring-offset-2" />
                                        <div class="size-8 rounded-lg bg-blue-600" />
                                        <div class="size-8 rounded-lg bg-amber-600" />
                                        <div class="size-8 rounded-lg bg-rose-600" />
                                        <div class="size-8 rounded-lg bg-violet-600" />
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-zinc-500">Logo de la agencia</label>
                                    <div class="mt-1 flex h-20 items-center justify-center rounded-lg border-2 border-dashed border-zinc-200 bg-zinc-50 text-sm text-zinc-400">
                                        Arrastra tu logo aquí
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-zinc-500">Subdominio</label>
                                    <div class="mt-1 flex overflow-hidden rounded-lg border border-zinc-200">
                                        <input
                                            type="text"
                                            value="eco-aventura"
                                            readonly
                                            class="flex-1 border-0 bg-white px-3 py-2 text-sm text-zinc-900 outline-none"
                                        />
                                        <span class="flex items-center bg-zinc-50 px-3 text-sm text-zinc-500">.montree.co</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -bottom-3 -right-3 -z-10 size-full rounded-2xl bg-emerald-100" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section id="benefits" class="bg-emerald-600 py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        ¿Por qué elegir Montree?
                    </h2>
                    <p class="mt-4 text-lg text-emerald-100">
                        Nos especializamos en agencias de ecoturismo. Conocemos tu negocio.
                    </p>
                </div>
                <div class="mx-auto mt-16 grid max-w-3xl gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="benefit in benefits"
                        :key="benefit.text"
                        class="flex items-center gap-3 rounded-xl bg-white/10 px-5 py-4 backdrop-blur-sm"
                    >
                        <component :is="benefit.icon" class="size-5 shrink-0 text-emerald-200" />
                        <span class="text-sm font-medium text-white">{{ benefit.text }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section id="contact" class="bg-white py-24 sm:py-32">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                    Digitaliza tu agencia hoy
                </h2>
                <p class="mt-4 text-lg text-zinc-600">
                    Únete a las agencias de ecoturismo que ya usan Montree para gestionar sus operaciones de forma más eficiente.
                </p>
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a
                        href="mailto:hola@montree.co"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-8 py-3.5 text-base font-semibold text-white shadow-lg shadow-emerald-200 transition hover:bg-emerald-700 hover:shadow-xl"
                    >
                        <Mail class="size-5" />
                        Contáctanos
                    </a>
                </div>
                <p class="mt-6 text-sm text-zinc-500">
                    Sin compromiso. Te respondemos en menos de 24 horas.
                </p>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-zinc-100 bg-zinc-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <div class="flex items-center gap-2.5">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                            <Leaf class="size-4" />
                        </div>
                        <span class="text-lg font-bold text-zinc-900">Montree</span>
                    </div>
                    <p class="text-sm text-zinc-500">
                        &copy; {{ new Date().getFullYear() }} Montree. Todos los derechos reservados.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
