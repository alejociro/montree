<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pause, Play } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import PlatformLegalEntity from '@/components/molecules/PlatformLegalEntity.vue';
import { useLegalLinks } from '@/composables/useLegalLinks';
import { useTranslations } from '@/composables/useTranslations';
import { home } from '@/routes';

interface Props {
    registerUrl: string;
    loginUrl: string;
    contactUrl: string;
    demoUrl: string;
}

const props = defineProps<Props>();
const { t } = useTranslations();
const page = usePage();

const contactEmail = computed(
    () => page.props.platform?.legal.email ?? 'it@jae-solutions.com',
);

const pillars = [
    { icon: '24/7', label: t('Reservas en línea') },
    { icon: 'ES', label: t('Soporte en español') },
];

// WHY: every sentence below describes something the code already does (see
// the feature audit of 2026-09-27). Do not add a feature here before it ships.
// `start` is the second where each chapter begins in
// public/landing/montree-funciones.mp4 (rendered with Remotion from the
// VideoMaker project). Re-render the video → re-check these marks.
const features = [
    {
        title: t('Catálogo'),
        start: 3,
        body: t(
            'Publica tus tours con galería de fotos, itinerario por etapas, nivel de dificultad, punto de encuentro, qué incluye y qué llevar.',
        ),
        blocks: [
            {
                title: t('Cupos en vivo'),
                description: t(
                    'cuando se llena, la salida se cierra sola. Sin sobreventas.',
                ),
            },
            {
                title: t('Fichas completas'),
                description: t(
                    'punto de encuentro, qué incluye y qué no, qué llevar y nivel de exigencia.',
                ),
            },
            {
                title: t('Lista para publicar'),
                description: t(
                    'una lista de verificación te dice qué le falta a un tour antes de publicarlo.',
                ),
            },
        ],
    },
    {
        title: t('Reservas 24/7'),
        start: 8.33,
        body: t(
            'El turista reserva y paga solo, a cualquier hora, desde el sitio de tu agencia.',
        ),
        blocks: [
            {
                title: t('Confirmación automática'),
                description: t(
                    'el turista recibe la confirmación por correo apenas se aprueba el pago.',
                ),
            },
            {
                title: t('Cupo retenido'),
                description: t(
                    'mientras paga, su cupo queda apartado y se libera solo si no completa el pago.',
                ),
            },
            {
                title: t('Recordatorios'),
                description: t(
                    'Montree le recuerda la salida al turista antes del viaje.',
                ),
            },
        ],
    },
    {
        title: t('Pagos'),
        start: 13.67,
        body: t(
            'Cobra en línea con PSE a través de PlacetoPay y registra los pagos que recibes en efectivo o por transferencia.',
        ),
        blocks: [
            {
                title: t('Pago en línea'),
                description: t(
                    'PSE procesado por PlacetoPay, con la confirmación de la reserva al aprobarse.',
                ),
            },
            {
                title: t('Abonos'),
                description: t(
                    'defines el porcentaje mínimo que el turista paga para reservar, por agencia o por salida.',
                ),
            },
            {
                title: t('Pagos manuales'),
                description: t(
                    'registras efectivo o transferencias y cada pago queda asociado a su reserva.',
                ),
            },
        ],
    },
    {
        title: t('Dashboard'),
        start: 19,
        body: t(
            'Ingresos, reservas, ocupación de las próximas salidas y tours más vendidos en una sola vista.',
        ),
        blocks: [
            {
                title: t('Ingresos'),
                description: t(
                    'cuánto entró en el periodo y por qué medio de pago.',
                ),
            },
            {
                title: t('Ocupación'),
                description: t(
                    'las salidas de los próximos 7 días con sus cupos vendidos.',
                ),
            },
            {
                title: t('Tours más vendidos'),
                description: t('qué experiencias mueven tu negocio.'),
            },
        ],
    },
    {
        title: t('Equipo'),
        start: 24.33,
        body: t(
            'Roles con permisos reales: cada persona ve exactamente lo que necesita para su trabajo.',
        ),
        blocks: [
            {
                title: t('Roles a tu medida'),
                description: t(
                    'Admin, Vendedor, Operador y Guía de base, y puedes crear roles propios.',
                ),
            },
            {
                title: t('Agenda del guía'),
                description: t(
                    'sus salidas y la lista de pasajeros, desde el navegador del celular.',
                ),
            },
            {
                title: t('Asignación de guías'),
                description: t(
                    'Montree revisa la disponibilidad para que un guía no quede en dos salidas a la vez.',
                ),
            },
        ],
    },
    {
        title: t('Tu sitio'),
        start: 29.67,
        body: t(
            'Tu agencia estrena página pública con tu logo, tus colores y tu catálogo, lista para recibir reservas.',
        ),
        blocks: [
            {
                title: t('Tu marca'),
                description: t(
                    'logo, colores, favicon e imagen principal propios.',
                ),
            },
            {
                title: t('Tu subdominio'),
                description: t(
                    'tuagencia.montree.co, activo desde el primer día.',
                ),
            },
            {
                title: t('Sin código'),
                description: t(
                    'lo editas desde el panel, sin depender de un desarrollador.',
                ),
            },
        ],
    },
];

// The last chapter ends where the closing card of the video starts.
const videoChaptersEnd = 35;

const video = ref<HTMLVideoElement | null>(null);
const isPlaying = ref(false);
const featureIdx = ref(0);
const activeFeature = computed(() => features[featureIdx.value]);

const syncChapter = () => {
    const currentTime = video.value?.currentTime ?? 0;

    if (currentTime >= videoChaptersEnd) {
        return;
    }

    const index = features.findLastIndex(
        (feature) => feature.start <= currentTime,
    );

    featureIdx.value = Math.max(index, 0);
};

const goToChapter = (index: number) => {
    featureIdx.value = index;

    if (!video.value) {
        return;
    }

    video.value.currentTime = features[index].start;
    void video.value.play();
};

const togglePlayback = () => {
    if (!video.value) {
        return;
    }

    if (video.value.paused) {
        void video.value.play();
    } else {
        video.value.pause();
    }
};

onMounted(() => {
    // WHY: autoplay is a motion effect; visitors who asked the OS for reduced
    // motion get the poster and press play themselves.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        video.value?.pause();

        return;
    }

    void video.value?.play().catch(() => {
        isPlaying.value = false;
    });
});

const tenantList = [
    t('Página pública propia con tu logo, tus colores y tus fotos'),
    t('Tu dirección tuagencia.montree.co, activa desde el primer día'),
    t('Catálogo, precios y políticas independientes de cualquier otra agencia'),
    t('Datos aislados: ninguna agencia ve la información de otra'),
    t('Editable sin código desde el panel, sin depender de un desarrollador'),
];

const roles = [
    { name: t('Admin'), description: t('todo el negocio') },
    { name: t('Guía'), description: t('su agenda') },
    { name: t('Vendedor'), description: t('ventas y reservas') },
    { name: t('Operador'), description: t('salidas del día') },
];

const productLinks = [
    { label: t('Funciones'), href: '#funciones' },
    { label: t('Tu sitio propio'), href: '#sitio' },
    { label: t('Preguntas frecuentes'), href: '/faq' },
];
const legalLinks = useLegalLinks();
</script>

<template>
    <Head :title="$t('Montree — Software para agencias de ecoturismo')" />

    <div
        class="landing-root min-h-screen overflow-x-hidden bg-[#F6EFE1] font-['IBM_Plex_Sans',system-ui,sans-serif] text-[#123524] antialiased"
    >
        <section
            class="relative isolate flex min-h-[clamp(560px,86vh,860px)] flex-col bg-[#123524]"
        >
            <picture>
                <source
                    type="image/avif"
                    media="(orientation: portrait) and (max-width: 639px)"
                    sizes="100vw"
                    srcset="
                        /landing/hero-portrait-540.avif   540w,
                        /landing/hero-portrait-720.avif   720w,
                        /landing/hero-portrait-1080.avif 1080w,
                        /landing/hero-portrait-1620.avif 1620w
                    "
                />
                <source
                    type="image/webp"
                    media="(orientation: portrait) and (max-width: 639px)"
                    sizes="100vw"
                    srcset="
                        /landing/hero-portrait-540.webp   540w,
                        /landing/hero-portrait-720.webp   720w,
                        /landing/hero-portrait-1080.webp 1080w,
                        /landing/hero-portrait-1620.webp 1620w
                    "
                />
                <source
                    type="image/avif"
                    sizes="100vw"
                    srcset="
                        /landing/hero-wide-640.avif   640w,
                        /landing/hero-wide-960.avif   960w,
                        /landing/hero-wide-1280.avif 1280w,
                        /landing/hero-wide-1600.avif 1600w,
                        /landing/hero-wide-1920.avif 1920w,
                        /landing/hero-wide-2560.avif 2560w,
                        /landing/hero-wide-3200.avif 3200w,
                        /landing/hero-wide-3840.avif 3840w
                    "
                />
                <source
                    type="image/webp"
                    sizes="100vw"
                    srcset="
                        /landing/hero-wide-640.webp   640w,
                        /landing/hero-wide-960.webp   960w,
                        /landing/hero-wide-1280.webp 1280w,
                        /landing/hero-wide-1600.webp 1600w,
                        /landing/hero-wide-1920.webp 1920w,
                        /landing/hero-wide-2560.webp 2560w,
                        /landing/hero-wide-3200.webp 3200w,
                        /landing/hero-wide-3840.webp 3840w
                    "
                />
                <img
                    src="/landing/hero-wide-1600.webp"
                    alt=""
                    fetchpriority="high"
                    decoding="async"
                    class="absolute inset-0 -z-10 size-full object-cover"
                />
            </picture>
            <div
                class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,rgb(10_32_20/52%)_0%,rgb(10_32_20/45%)_45%,rgb(10_32_20/78%)_100%)]"
            ></div>
            <header
                class="relative z-20 mx-auto flex w-full max-w-[1320px] items-center gap-[18px] px-[clamp(16px,4vw,44px)] py-[22px] text-[#FBF6EC]"
            >
                <Link :href="home().url" class="flex items-center gap-2.5">
                    <span
                        class="grid size-8 place-items-center rounded-full bg-[#FBF6EC] font-['Newsreader',serif] text-base font-semibold text-[#123524]"
                        >M</span
                    >
                    <span
                        class="font-['Newsreader',serif] text-[21px] font-medium tracking-[0.01em]"
                        >Montree</span
                    >
                </Link>

                <Link
                    :href="props.loginUrl"
                    class="ml-auto text-[13px] whitespace-nowrap text-[#FBF6EC]/85 transition-colors hover:text-[#E9D9B4]"
                    >{{ $t('Iniciar sesión') }}</Link
                >
                <Link
                    :href="props.registerUrl"
                    class="rounded-full bg-[#FBF6EC] px-[clamp(14px,3vw,22px)] py-[11px] text-[13px] font-medium whitespace-nowrap text-[#123524] transition-colors hover:bg-white"
                    >{{ $t('Comenzar gratis') }}</Link
                >
            </header>

            <div
                class="relative z-10 mx-auto my-auto w-full max-w-[900px] px-[clamp(16px,4vw,44px)] pt-[clamp(24px,4vw,48px)] pb-[clamp(150px,18vw,260px)] text-center text-[#FBF6EC]"
            >
                <h1
                    class="font-['Newsreader',serif] text-[clamp(42px,6.4vw,84px)] leading-[1.04] font-semibold italic drop-shadow-[0_8px_34px_rgba(10,32,20,0.5)]"
                >
                    {{ $t('Digitaliza tu agencia') }}<br />{{
                        $t('de ecoturismo')
                    }}
                </h1>
                <p
                    class="mx-auto mt-[18px] max-w-[52ch] text-[15.5px] leading-[1.65] text-[#FBF6EC]/90"
                >
                    {{
                        $t(
                            'Reservas automáticas, pagos en línea, equipo coordinado y tu propia página pública con tu marca. Todo en un solo lugar.',
                        )
                    }}
                </p>
                <div class="mt-[26px] flex flex-wrap justify-center gap-3">
                    <Link
                        :href="props.registerUrl"
                        class="rounded-full bg-[#FBF6EC] px-7 py-3.5 text-[13.5px] font-medium text-[#123524] transition-colors hover:bg-white"
                        >{{ $t('Crear mi agencia') }}</Link
                    >
                    <a
                        :href="props.demoUrl"
                        class="inline-flex items-center gap-2 rounded-full border border-[#FBF6EC]/55 px-[26px] py-3.5 text-[13.5px] text-[#FBF6EC] transition-colors hover:bg-[#FBF6EC]/16"
                    >
                        <Play class="size-3.5 fill-current" />
                        {{ $t('Ver cómo funciona') }}
                    </a>
                </div>
            </div>

            <svg
                viewBox="0 0 1440 150"
                preserveAspectRatio="none"
                class="absolute -bottom-px left-0 block h-[clamp(70px,10vw,150px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,52 C210,132 400,4 700,58 C980,108 1180,18 1440,66 L1440,150 L0,150 Z"
                    fill="#F6EFE1"
                />
            </svg>
        </section>

        <section
            id="funciones"
            class="relative z-10 mx-auto -mt-[clamp(110px,14vw,200px)] max-w-[1180px] scroll-mt-6 px-[clamp(16px,4vw,44px)] pb-[clamp(52px,7vw,96px)]"
        >
            <div
                class="relative overflow-hidden rounded-[clamp(18px,2.4vw,32px)] border border-[#123524]/10 bg-[#FBF6EC] shadow-[0_40px_80px_-44px_rgba(10,32,20,0.6)]"
            >
                <video
                    ref="video"
                    class="block aspect-video w-full"
                    src="/landing/montree-funciones.mp4"
                    poster="/landing/montree-funciones-poster.jpg"
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    :aria-label="
                        $t(
                            'Video: recorrido por las funciones de Montree — catálogo, reservas, pagos, dashboard, equipo y sitio propio',
                        )
                    "
                    @timeupdate="syncChapter"
                    @play="isPlaying = true"
                    @pause="isPlaying = false"
                />
                <button
                    type="button"
                    class="absolute right-[clamp(10px,1.6vw,20px)] bottom-[clamp(10px,1.6vw,20px)] grid size-10 place-items-center rounded-full bg-[#123524]/80 text-[#FBF6EC] backdrop-blur-[6px] transition-colors hover:bg-[#123524]"
                    :aria-label="
                        isPlaying ? $t('Pausar video') : $t('Reproducir video')
                    "
                    @click="togglePlayback"
                >
                    <Pause v-if="isPlaying" class="size-4 fill-current" />
                    <Play v-else class="size-4 fill-current" />
                </button>
            </div>

            <div class="mt-[clamp(28px,4vw,48px)] text-center">
                <h2
                    class="font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                >
                    {{ $t('Todo lo que tu agencia necesita') }}
                </h2>
                <p
                    class="mx-auto mt-3 max-w-[56ch] text-[14.5px] leading-[1.7] text-[#5C6E60]"
                >
                    {{
                        $t(
                            'Seis módulos que trabajan juntos. Elige uno para verlo en el video.',
                        )
                    }}
                </p>
            </div>

            <div
                class="mt-[clamp(20px,3vw,32px)] flex flex-wrap justify-center gap-2"
                role="group"
                :aria-label="$t('Funciones de Montree')"
            >
                <button
                    v-for="(feature, index) in features"
                    :key="feature.title"
                    type="button"
                    class="rounded-full px-5 py-[11px] text-sm transition-colors duration-150"
                    :class="
                        index === featureIdx
                            ? 'bg-[#123524] text-[#F6EFE1]'
                            : 'border border-[#123524]/10 bg-[#FBF6EC] text-[#3C5445] hover:bg-[#E4EDE0]'
                    "
                    :aria-pressed="index === featureIdx"
                    aria-controls="feature-panel"
                    @click="goToChapter(index)"
                >
                    {{ feature.title }}
                </button>
            </div>

            <div
                id="feature-panel"
                class="mt-[clamp(20px,3vw,32px)]"
                aria-live="polite"
            >
                <p
                    class="mx-auto max-w-[62ch] text-center text-[15px] leading-[1.7] text-[#4A5C4E]"
                >
                    {{ activeFeature.body }}
                </p>
                <div class="mt-6 grid gap-3.5 md:grid-cols-3">
                    <div
                        v-for="block in activeFeature.blocks"
                        :key="block.title"
                        class="rounded-[24px] border border-[#123524]/9 bg-[#FBF6EC] px-[22px] py-[18px]"
                    >
                        <p
                            class="font-['Newsreader',serif] text-[20px] font-semibold text-[#2C5C3C] italic"
                        >
                            {{ block.title }}
                        </p>
                        <p
                            class="mt-1 text-[14px] leading-[1.6] text-[#4A5C4E] first-letter:uppercase"
                        >
                            {{ block.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="relative bg-[#123524] text-[#FBF6EC]">
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -top-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,0 L1440,0 L1440,58 C1200,10 1010,120 720,66 C420,10 220,128 0,52 Z"
                    fill="#F6EFE1"
                />
            </svg>

            <div
                class="relative z-10 mx-auto max-w-[760px] px-[clamp(16px,4vw,44px)] py-[clamp(90px,13vw,190px)] text-center"
            >
                <div>
                    <h2
                        class="font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                    >
                        {{ $t('¿Te suena familiar?') }}
                    </h2>
                    <p
                        class="mx-auto mt-[18px] max-w-[58ch] text-[15px] leading-[1.75] text-[#FBF6EC]/90"
                    >
                        {{
                            $t(
                                'Seis chats de WhatsApp por la misma reserva. Un Excel que solo entiende una persona. Dos vendedores prometiendo el mismo cupo del sábado. Y al final del mes, ninguna certeza de qué tour deja plata.',
                            )
                        }}
                    </p>
                    <p
                        class="mx-auto mt-3.5 max-w-[58ch] text-[15px] leading-[1.75] text-[#FBF6EC]/90"
                    >
                        {{
                            $t(
                                'Montree ordena esa operación: cada reserva con su pago, su salida y su estado, visible para tu equipo desde el panel.',
                            )
                        }}
                    </p>
                    <div class="mt-7 flex flex-wrap justify-center gap-2.5">
                        <span
                            v-for="pillar in pillars"
                            :key="pillar.label"
                            class="inline-flex items-center gap-2.5 rounded-full bg-[#FBF6EC]/9 py-2 pr-4 pl-2 text-[12.5px] text-[#FBF6EC]/90"
                        >
                            <span
                                class="rounded-full bg-[#E9D9B4]/16 px-2.5 py-1 font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.08em] text-[#E9D9B4]"
                                >{{ pillar.icon }}</span
                            >
                            {{ pillar.label }}
                        </span>
                    </div>
                </div>
            </div>

            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -bottom-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,46 C230,120 420,8 720,56 C1010,102 1210,14 1440,58 L1440,130 L0,130 Z"
                    fill="#F6EFE1"
                />
            </svg>
        </section>

        <section
            id="sitio"
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] py-[clamp(52px,7vw,96px)]"
        >
            <div class="grid gap-[clamp(26px,4vw,64px)] lg:grid-cols-2">
                <div>
                    <h2
                        class="max-w-[16ch] font-['Newsreader',serif] text-[clamp(34px,4.8vw,58px)] leading-[1.05] font-semibold italic"
                    >
                        {{ $t('Tu propio sitio, tu propia marca') }}
                    </h2>
                    <p
                        class="mt-[18px] max-w-[52ch] text-[15px] leading-[1.75] text-[#4A5C4E]"
                    >
                        {{
                            $t(
                                'Montree es multi-tenant: al registrarte se crea un espacio aislado para tu agencia, con su página pública, su catálogo, sus precios y sus usuarios. Tus turistas ven tu marca — nunca la nuestra. Esta página es de Montree; la de tu agencia es tuya.',
                            )
                        }}
                    </p>
                    <div class="mt-7">
                        <p
                            class="font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.14em] text-[#5C6E60]"
                        >
                            {{ $t('ROLES EN CADA AGENCIA') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2.5">
                            <span
                                v-for="role in roles"
                                :key="role.name"
                                class="inline-flex items-center gap-1.5 rounded-full border border-[#123524]/10 bg-[#FBF6EC] px-[15px] py-[9px] text-[12.5px]"
                            >
                                <strong class="font-semibold">{{
                                    role.name
                                }}</strong>
                                <span class="text-[#5C6E60]"
                                    >· {{ role.description }}</span
                                >
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-[32px] border border-[#123524]/9 bg-[#FBF6EC] p-[clamp(22px,3vw,36px)]"
                >
                    <ul class="flex flex-col gap-3.5">
                        <li
                            v-for="item in tenantList"
                            :key="item"
                            class="flex items-start gap-3 text-[14.5px] leading-[1.55] text-[#2A3E30]"
                        >
                            <span
                                class="mt-1.5 size-2 flex-none rotate-45 rounded-[2px] bg-[#2C5C3C]"
                            />
                            {{ item }}
                        </li>
                    </ul>
                    <a
                        :href="props.demoUrl"
                        class="mt-7 inline-block rounded-full bg-[#123524] px-[26px] py-[13px] text-[13.5px] text-[#F6EFE1] transition-colors hover:bg-[#2C5C3C]"
                        @click="goToChapter(features.length - 1)"
                        >{{ $t('Verlo en el video') }}</a
                    >
                </div>
            </div>
        </section>

        <section
            id="registro"
            class="relative overflow-hidden bg-[#123524] text-[#FBF6EC]"
        >
            <svg
                viewBox="0 0 1440 130"
                preserveAspectRatio="none"
                class="absolute -top-px left-0 z-20 block h-[clamp(60px,8vw,130px)] w-full"
                aria-hidden="true"
            >
                <path
                    d="M0,0 L1440,0 L1440,50 C1190,116 1000,6 700,60 C410,112 210,12 0,60 Z"
                    fill="#F6EFE1"
                />
            </svg>
            <span
                class="absolute top-[52%] left-1/2 size-[clamp(300px,40vw,540px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#FBF6EC]/12"
            />
            <span
                class="absolute top-[52%] left-1/2 size-[clamp(180px,24vw,340px)] -translate-x-1/2 -translate-y-1/2 rounded-full border border-[#FBF6EC]/9"
            />

            <div
                class="relative z-10 mx-auto max-w-[660px] px-[clamp(16px,4vw,44px)] pt-[clamp(110px,15vw,190px)] pb-[clamp(70px,9vw,120px)] text-center"
            >
                <p
                    class="font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.18em] text-[#E9D9B4]"
                >
                    {{ $t('MONTREE · BETA ABIERTA') }}
                </p>
                <h2
                    class="mt-[18px] font-['Newsreader',serif] text-[clamp(36px,5.6vw,68px)] leading-[1.04] font-semibold italic"
                >
                    {{ $t('¿Listo para dejar el Excel atrás?') }}
                </h2>
                <p
                    class="mt-[18px] text-[15.5px] leading-[1.7] text-[#FBF6EC]/84"
                >
                    {{
                        $t(
                            'Crear tu agencia no tiene costo. Montree cobra un porcentaje solo por reserva confirmada, según el valor de la reserva.',
                        )
                    }}
                </p>
                <div class="mt-7 flex flex-wrap justify-center gap-3">
                    <Link
                        :href="props.registerUrl"
                        class="rounded-full bg-[#F6EFE1] px-8 py-[15px] text-[13.5px] font-medium text-[#123524] transition-colors hover:bg-white"
                        >{{ $t('Comenzar gratis') }}</Link
                    >
                    <a
                        :href="props.contactUrl"
                        target="_blank"
                        rel="noopener"
                        class="rounded-full border border-[#FBF6EC]/45 px-7 py-[15px] text-[13.5px] transition-colors hover:bg-[#FBF6EC]/14"
                        >{{ $t('Hablar con el equipo') }}</a
                    >
                </div>
                <p
                    class="mt-[18px] font-['IBM_Plex_Mono',monospace] text-[11px] text-[#FBF6EC]/55"
                >
                    {{ $t('SIN TARJETA DE CRÉDITO · SIN PERMANENCIA MÍNIMA') }}
                </p>
            </div>
        </section>

        <footer
            class="mx-auto max-w-[1220px] px-[clamp(16px,4vw,44px)] pt-[clamp(38px,5vw,64px)] pb-[26px]"
        >
            <div
                class="grid grid-cols-[repeat(auto-fit,minmax(190px,1fr))] gap-8"
            >
                <div class="max-w-[32ch]">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="grid size-7 place-items-center rounded-full bg-[#123524] font-['Newsreader',serif] text-sm font-semibold text-[#F6EFE1]"
                            >M</span
                        >
                        <span
                            class="font-['Newsreader',serif] text-xl font-medium"
                            >Montree</span
                        >
                    </div>
                    <p class="mt-3.5 text-[13px] leading-[1.65] text-[#5C6E60]">
                        {{
                            $t(
                                'La plataforma digital para agencias de ecoturismo que quieren crecer sin caos administrativo.',
                            )
                        }}
                    </p>
                    <a
                        :href="`mailto:${contactEmail}`"
                        class="mt-2.5 block text-[13px] transition-colors hover:text-[#2C5C3C]"
                        >{{ contactEmail }}</a
                    >
                </div>
                <nav :aria-label="$t('Producto')">
                    <p
                        class="font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.14em] text-[#5C6E60]"
                    >
                        {{ $t('PRODUCTO') }}
                    </p>
                    <div class="mt-3.5 flex flex-col gap-[9px] text-[13px]">
                        <a
                            v-for="link in productLinks"
                            :key="link.href"
                            :href="link.href"
                            class="transition-colors hover:text-[#2C5C3C]"
                            >{{ link.label }}</a
                        >
                    </div>
                </nav>
                <nav :aria-label="$t('Legal')">
                    <p
                        class="font-['IBM_Plex_Mono',monospace] text-[11px] tracking-[0.14em] text-[#5C6E60]"
                    >
                        {{ $t('LEGAL') }}
                    </p>
                    <div class="mt-3.5 flex flex-col gap-[9px] text-[13px]">
                        <a
                            v-for="link in legalLinks"
                            :key="link.href"
                            :href="link.href"
                            class="transition-colors hover:text-[#2C5C3C]"
                            >{{ link.label }}</a
                        >
                    </div>
                </nav>
            </div>
            <div
                class="mt-8 border-t border-[#123524]/12 pt-[18px] text-[12px] leading-[1.6] text-[#5C6E60]"
            >
                <PlatformLegalEntity inline />
            </div>
            <div
                class="mt-3 flex flex-wrap justify-between gap-3 font-['IBM_Plex_Mono',monospace] text-[11px] text-[#5C6E60]"
            >
                <span>{{
                    $t('© :year MONTREE · TODOS LOS DERECHOS RESERVADOS', {
                        year: new Date().getFullYear(),
                    })
                }}</span>
                <span>{{ $t('HECHO PARA EL ECOTURISMO COLOMBIANO') }}</span>
            </div>
        </footer>
    </div>
</template>

<style scoped>
/* WHY: foco visible sobre fondos crema y verde por igual (WCAG 2.4.7). */
.landing-root :is(a, button, video):focus-visible {
    outline: 2px solid #2c5c3c;
    outline-offset: 3px;
    box-shadow: 0 0 0 5px #fbf6ec;
}
</style>
